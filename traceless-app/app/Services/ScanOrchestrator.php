<?php

namespace App\Services;

use App\Events\AlertCreated;
use App\Events\FindingDiscovered;
use App\Events\ReportGenerated;
use App\Events\ScanCompleted;
use App\Events\ScanProgressUpdated;
use App\Jobs\RunScanJob;
use App\Mail\DeepScanStartedMail;
use App\Models\Alert;
use App\Models\CredentialExposure;
use App\Models\Finding;
use App\Models\IdentityProfile;
use App\Models\RemovalInstruction;
use App\Models\RemovalTask;
use App\Models\Scan;
use App\Models\ScanSource;
use App\Notifications\AlertNotification;
use App\Notifications\ScanCompletedNotification;
use App\Support\ScanBudgetExceededException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class ScanOrchestrator
{
    public function __construct(
        protected RiskScoringService $riskScorer,
        protected ConsentService $consent,
        protected DemoScanAdapter $demoAdapter,
        protected PythonScannerClient $pythonScanner,
        protected ReportService $reports,
    ) {}

    /**
     * Create a scan and hand it to the queue. Consent is re-validated here so
     * no caller can bypass the gate by dispatching a scan directly.
     */
    public function queueScan(IdentityProfile $profile, string $type = 'snapshot'): Scan
    {
        if (! $this->consent->hasValidConsent($profile)) {
            throw new RuntimeException('A recorded consent event is required before scanning this identity.');
        }

        $sourceCount = config('traceless.demo_mode')
            ? count(DemoScanAdapter::SOURCE_CATALOG) + 1
            : 0;

        $scan = Scan::create([
            'identity_profile_id' => $profile->id,
            'type' => $type,
            'status' => 'queued',
            'progress_percentage' => 0,
            'current_stage' => config('traceless.demo_mode')
                ? 'Queued for public-source scan'
                : 'Queued for verified public-source scan',
            'demo_mode' => (bool) config('traceless.demo_mode'),
            'sources_total' => $sourceCount,
            'sources_checked' => 0,
            'findings_count' => 0,
        ]);

        // Tell the account email that a deep scan started, with the honest
        // upper bound. Queued so a slow mail provider never delays the redirect.
        if ($type === 'deep_scan' && ($email = $profile->user?->email)) {
            try {
                Mail::to($email)->queue(new DeepScanStartedMail($scan, $scan->estimatedDurationSeconds()));
            } catch (Throwable $exception) {
                Log::warning('deep_scan_started_mail_failed', [
                    'scan_id' => $scan->id,
                    'reason' => get_class($exception),
                ]);
            }
        }

        RunScanJob::dispatch($scan)->onQueue('scans');

        return $scan;
    }

    public function executeScan(Scan $scan): void
    {
        $scan->refresh();

        // A late or duplicated delivery must not resurrect a scan that a retry,
        // the stale-scan reaper or an administrator already finished.
        if (! $scan->isActive()) {
            return;
        }

        // Deep scans get one wall-clock deadline for the whole job (discovery
        // plus source processing). It stays below the 600s queue timeout, so a
        // slow scan is failed cleanly instead of the worker being killed while
        // it is writing findings.
        $deadline = $this->scanDeadline($scan);

        try {
            $profile = $scan->identityProfile;
            $this->setStage($scan, 'initializing', 3, 'Initializing consent-aware scan engine...', [
                'started_at' => now(),
            ]);

            $this->delay();

            $this->setStage($scan, 'checking_sources', 10, config('traceless.demo_mode')
                ? 'Checking approved public sources. Public information only.'
                : 'Querying approved public sources and matching their existence rules...', [
                    'started_at' => $scan->started_at ?? now(),
                ]);

            $report = $this->discoverFindings($profile, $scan->type === 'deep_scan' ? 'deep' : 'curated', $scan, $deadline);
            $findings = $report['findings'];

            if ($this->wasCancelled($scan)) {
                return;
            }

            $this->guardScanDeadline($deadline);

            if (! config('traceless.demo_mode') && $report['attempted'] > 0 && $report['sources'] === []) {
                throw new RuntimeException('No monitored identifier could be checked.');
            }

            $grouped = collect($findings)->groupBy('source');

            $this->createScanSources($scan, $report['sources']);
            $scan->update(['sources_total' => $scan->scanSources()->count()]);
            $inserted = 0;
            $reappeared = 0;
            $sources = $scan->scanSources()->orderBy('id')->get();
            $total = max(1, $sources->count());
            $checked = 0;

            foreach ($sources as $source) {
                if ($this->wasCancelled($scan)) {
                    return;
                }

                $this->guardScanDeadline($deadline);

                if ($source->outcome === 'skipped') {
                    $source->update(['status' => 'completed', 'findings_found' => 0]);
                    $this->setStage(
                        $scan,
                        'checking_sources',
                        max(10, (int) round((($checked + 1) / $total) * 62)),
                        "Skipped {$source->name} · not configured",
                        ['sources_checked' => $checked]
                    );
                    $checked++;

                    continue;
                }

                $source->update(['status' => 'checking']);
                $this->delay();

                $sourceFindings = collect();
                if ($source->category === 'credentials') {
                    $inserted += $this->storeCredentialExposures($profile);
                } else {
                    $sourceFindings = $grouped->get($source->name, collect());
                    foreach ($sourceFindings as $row) {
                        [$wasInserted, $wasReappeared] = $this->storeFinding($scan, $profile, $row);
                        $inserted += $wasInserted ? 1 : 0;
                        $reappeared += $wasReappeared ? 1 : 0;
                    }
                }

                $checked++;
                $source->update([
                    'status' => 'completed',
                    'findings_found' => $sourceFindings->count(),
                ]);

                $progress = 10 + (int) round(($checked / $total) * 52);
                $this->setStage(
                    $scan,
                    'checking_sources',
                    $progress,
                    "Checked {$source->name} · {$checked}/{$total} public sources",
                    [
                        'sources_checked' => $checked,
                        'findings_count' => $inserted + $reappeared,
                    ]
                );
            }

            // Any findings from a source not in the catalog still get stored.
            $this->guardScanDeadline($deadline);

            foreach ($grouped as $sourceName => $rows) {
                if ($sources->contains(fn (ScanSource $s) => $s->name === $sourceName)) {
                    continue;
                }
                foreach ($rows as $row) {
                    [$wasInserted, $wasReappeared] = $this->storeFinding($scan, $profile, $row);
                    $inserted += $wasInserted ? 1 : 0;
                    $reappeared += $wasReappeared ? 1 : 0;
                }
            }

            // A confirmed absence is evidence too: do not keep presenting a
            // previously found record as current when the source said no.
            $this->reconcileAbsentFindings($profile, $report['sources'] ?? []);

            if ($this->wasCancelled($scan)) {
                return;
            }

            $this->guardScanDeadline($deadline);

            $this->setStage($scan, 'normalizing_results', 70, 'De-duplicating records and validating confidence thresholds...', [
                'sources_checked' => $total,
                'findings_count' => $inserted + $reappeared,
            ]);
            $this->delay();

            $this->setStage($scan, 'calculating_risk', 84, 'Calculating your 0–100 privacy posture estimate...');
            $this->delay();

            $this->guardScanDeadline($deadline);

            $score = $this->riskScorer->recalculateAndPersist($profile->id);

            $this->setStage($scan, 'generating_report', 93, 'Generating a signed privacy report...');

            $this->guardScanDeadline($deadline);

            $report = $this->reports->generate($profile, $scan->type, $scan->id);
            event(new ReportGenerated($report));

            $scan->update([
                'status' => 'completed',
                'progress_percentage' => 100,
                'current_stage' => 'Scan complete. Findings indexed and report ready.',
                'findings_count' => $inserted + $reappeared,
                'completed_at' => now(),
            ]);

            // Demo and live scans both advance the monitoring clock and notify the
            // user when something new appears. Without this, "continuous
            // monitoring" is a promise the product never delivers on.
            $this->emitMonitoringAlerts($profile, $scan, $inserted + $reappeared);

            event(new ScanCompleted($scan->fresh()));
            $scan->identityProfile->user->notify(new ScanCompletedNotification($scan->fresh()));

            Log::info('scan_completed', [
                'scan_id' => $scan->id,
                'profile_id' => $profile->id,
                'inserted' => $inserted,
                'reappeared' => $reappeared,
                'demo_mode' => $scan->demo_mode,
                'score' => $score['privacy_score'],
            ]);
        } catch (ScanBudgetExceededException $e) {
            // A budget stop is an expected outcome, not a queue failure: the
            // scan is failed with a message that names the budget and the job
            // exits successfully before the worker timeout.
            Log::warning('scan_budget_exceeded', [
                'scan_id' => $scan->id,
                'budget_seconds' => $e->budgetSeconds,
            ]);

            $scan->update([
                'status' => 'failed',
                'current_stage' => 'Deep scan stopped at its wall-clock budget.',
                'error_message' => $e->getMessage().' No report was generated. Retry with fewer monitored identifiers.',
                'completed_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('scan_failed', [
                'scan_id' => $scan->id,
                'exception' => get_class($e),
                'error_hash' => hash('sha256', $e->getMessage()),
            ]);

            $scan->update([
                'status' => 'failed',
                'current_stage' => 'Scan could not be completed.',
                'error_message' => 'The scan integration did not return a valid result. No personal data was retained. Please retry or contact support.',
                'completed_at' => now(),
            ]);
        }
    }

    public function cancel(Scan $scan): void
    {
        if (! $scan->isActive()) {
            return;
        }

        $scan->update([
            'status' => 'cancelled',
            'current_stage' => 'Scan cancelled by user.',
            'cancelled_at' => now(),
            'completed_at' => now(),
        ]);
    }

    /**
     * @return array{findings: array<int, array<string, mixed>>, sources: array<int, array<string, mixed>>|null, attempted: int}
     */
    protected function discoverFindings(IdentityProfile $profile, string $mode = 'curated', ?Scan $scan = null, ?Carbon $deadline = null): array
    {
        if (config('traceless.demo_mode')) {
            return ['findings' => $this->demoAdapter->findingsFor($profile), 'sources' => null, 'attempted' => 0];
        }

        $usernames = $profile->publicIdentifiers()
            ->where('type', 'username')
            ->where('is_monitored', true)
            ->pluck('value')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->take(max(1, (int) config('traceless.scan.max_usernames', 5)))
            ->values();

        $identifiers = $usernames->map(fn ($value) => ['username', $value]);

        // A deep scan also probes the address itself. Those checks prove an
        // account exists rather than exposing data, so the worker marks every
        // result unverified and the UI presents it that way.
        if ($mode === 'deep') {
            $emails = $profile->publicIdentifiers()
                ->where('type', 'email')
                ->where('is_monitored', true)
                ->pluck('value')
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->unique()
                ->take(max(1, (int) config('traceless.scan.max_emails', 1)))
                ->values();

            $identifiers = $identifiers->merge($emails->map(fn ($value) => ['email', $value]));

            $phones = $profile->publicIdentifiers()
                ->where('type', 'phone')
                ->where('is_monitored', true)
                ->pluck('value')
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->unique()
                ->take(max(1, (int) config('traceless.scan.max_phones', 1)))
                ->values();

            $identifiers = $identifiers->merge($phones->map(fn ($value) => ['phone', $value]));
        }

        if ($identifiers->isEmpty()) {
            return ['findings' => [], 'sources' => [], 'attempted' => 0];
        }

        $findings = [];
        $sources = [];
        $total = $identifiers->count();
        $checked = 0;

        // Every monitored identifier is checked. Each call is its own worker
        // process, and the caps in config keep the runtime bounded. The scan row
        // is touched between calls so a long deep scan is never mistaken for a
        // dead one by scans:reap-stale or activeScan().
        foreach ($identifiers as [$type, $value]) {
            if ($scan && $this->wasCancelled($scan)) {
                return ['findings' => [], 'sources' => [], 'attempted' => $total];
            }

            // Stop before a new subprocess starts once the deep-scan deadline
            // has passed; the remaining budget also caps that subprocess so it
            // cannot run past the deadline either.
            $this->guardScanDeadline($deadline);

            $timeout = $this->remainingIdentifierTimeout($deadline);
            $report = null;

            try {
                $report = $timeout === null
                    ? $this->pythonScanner->scanReport($type, $value, true, $mode)
                    : $this->pythonScanner->scanReport($type, $value, true, $mode, $timeout);
            } catch (ScanBudgetExceededException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                // One identifier failing must not discard the others.
                Log::warning('scan_identifier_failed', [
                    'type' => $type,
                    'mode' => $mode,
                    'reason' => get_class($exception),
                ]);
            }

            if ($report !== null) {
                foreach ($report['findings'] ?? [] as $finding) {
                    $findings[] = $finding;
                }

                $sources = $this->mergeSources($sources, $report['sources'] ?? []);
            }

            $checked++;

            if ($scan) {
                $scan->update([
                    'current_stage' => "Checked {$checked}/{$total} monitored identifiers · {$type}",
                ]);
            }
        }

        return ['findings' => $findings, 'sources' => array_values($sources), 'attempted' => $total];
    }

    /**
     * Collapse per-username source telemetry into one row per source, keeping
     * the strongest outcome observed (found > unknown > not_found > skipped).
     *
     * @param  array<string, array<string, mixed>>  $carry
     * @param  array<int, array<string, mixed>>  $incoming
     * @return array<string, array<string, mixed>>
     */
    protected function mergeSources(array $carry, array $incoming): array
    {
        $rank = ['found' => 3, 'unknown' => 2, 'not_found' => 1, 'skipped' => 0];

        foreach ($incoming as $source) {
            $name = $source['name'] ?? null;

            if (! $name) {
                continue;
            }

            if (! isset($carry[$name])) {
                $carry[$name] = $source;

                continue;
            }

            $existing = $rank[$carry[$name]['outcome'] ?? 'unknown'] ?? 0;
            $candidate = $rank[$source['outcome'] ?? 'unknown'] ?? 0;

            if ($candidate > $existing) {
                $carry[$name] = $source;
            }
        }

        return $carry;
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $sources
     */
    protected function createScanSources(Scan $scan, ?array $sources = null): void
    {
        if ($scan->scanSources()->exists()) {
            return;
        }

        if ($sources === null) {
            foreach (DemoScanAdapter::SOURCE_CATALOG as $source) {
                ScanSource::create([
                    'scan_id' => $scan->id,
                    'name' => $source['name'],
                    'category' => $source['category'],
                    'status' => 'pending',
                ]);
            }

            ScanSource::create([
                'scan_id' => $scan->id,
                'name' => 'Authorized breach metadata',
                'category' => 'credentials',
                'status' => 'pending',
            ]);

            return;
        }

        // Live mode: only sources the worker actually contacted are listed.
        foreach ($sources as $source) {
            ScanSource::create([
                'scan_id' => $scan->id,
                'name' => $source['name'],
                'category' => $source['category'],
                'status' => 'pending',
                'outcome' => $source['outcome'] ?? 'unknown',
                'http_status' => $source['http_status'] ?? null,
                'checked_url' => $source['checked_url'] ?? null,
                'outcome_reason' => $source['reason'] ?? null,
            ]);
        }

        ScanSource::create([
            'scan_id' => $scan->id,
            'name' => 'Breach metadata provider',
            'category' => 'credentials',
            'status' => 'completed',
            'outcome' => 'skipped',
            'outcome_reason' => 'provider_not_configured',
        ]);
    }

    /**
     * @return array{0: bool, 1: bool}
     */
    protected function storeFinding(Scan $scan, IdentityProfile $profile, array $row): array
    {
        $existing = Finding::where('identity_profile_id', $profile->id)
            ->where('source', $row['source'])
            ->where('identifier_value', $row['identifier_value'])
            ->first();

        if ($existing) {
            $wasRemoved = $existing->status === 'removed';

            $existing->update([
                'last_checked_at' => now(),
                'scan_id' => $scan->id,
                'status' => $wasRemoved ? 'reappeared' : $existing->status,
                ...$this->verificationColumns($row),
            ]);

            if ($wasRemoved) {
                $this->reopenOrCreateRemovalTask($profile, $existing);
                $this->createReappearanceAlert($profile, $existing);

                return [false, true];
            }

            return [false, false];
        }

        $finding = Finding::create([
            'identity_profile_id' => $profile->id,
            'scan_id' => $scan->id,
            'source' => $row['source'],
            'category' => $row['category'] ?? 'social_profile',
            'identifier_type' => $row['identifier_type'] ?? 'username',
            'identifier_value' => $row['identifier_value'] ?? '',
            'url' => $row['url'] ?? null,
            'confidence' => $row['confidence'] ?? 0.75,
            'risk' => $row['risk'] ?? 'moderate',
            'removal_method' => $row['removal_method'] ?? 'manual_instructions',
            'status' => $row['status'] ?? 'needs_user_action',
            'title' => $row['title'] ?? 'Public exposure',
            'description' => $row['description'] ?? null,
            'action_guidance' => $row['action_guidance'] ?? null,
            'discovered_at' => now(),
            'last_checked_at' => now(),
            ...$this->verificationColumns($row),
        ]);

        if (in_array($finding->risk, ['high', 'critical'], true) || $finding->removal_method === 'automated_request') {
            $this->ensureRemovalTask($profile, $finding);
        }

        if ($finding->risk === 'critical') {
            $this->createAlert(
                $profile,
                'new_exposure',
                'critical',
                'Critical public exposure discovered',
                "A critical-risk record was found on {$finding->source}. Review the finding and start the removal workflow.",
                route('findings.show', $finding)
            );
        }

        event(new FindingDiscovered($finding));

        return [true, false];
    }

    /**
     * @return array<string, mixed>
     */
    protected function verificationColumns(array $row): array
    {
        $status = $row['verification_status']
            ?? (config('traceless.demo_mode') ? 'demo' : 'unverified');

        if (! in_array($status, ['verified', 'unverified', 'demo'], true)) {
            $status = 'unverified';
        }

        $evidence = is_array($row['evidence'] ?? null) ? $row['evidence'] : null;

        return [
            'verification_status' => $status,
            'evidence' => $evidence,
            'http_status' => isset($evidence['http_status']) ? (int) $evidence['http_status'] : null,
            'verified_at' => $status === 'verified' ? now() : null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sources
     */
    protected function reconcileAbsentFindings(IdentityProfile $profile, array $sources): void
    {
        if (config('traceless.demo_mode')) {
            return;
        }

        $absent = collect($sources)
            ->where('outcome', 'not_found')
            ->pluck('name')
            ->filter()
            ->unique()
            ->values();

        if ($absent->isEmpty()) {
            return;
        }

        Finding::where('identity_profile_id', $profile->id)
            ->whereIn('source', $absent->all())
            ->where('verification_status', '!=', 'demo')
            ->whereNotIn('status', ['removed', 'reappeared'])
            ->update([
                'verification_status' => 'verified',
                'status' => 'could_not_verify',
                'verified_at' => now(),
                'last_checked_at' => now(),
            ]);
    }

    protected function ensureRemovalTask(IdentityProfile $profile, Finding $finding): void
    {
        if ($finding->removalTask()->exists()) {
            return;
        }

        $instruction = RemovalInstruction::where('source_identifier', str($finding->source)->slug()->toString())->first();

        RemovalTask::create([
            'identity_profile_id' => $profile->id,
            'finding_id' => $finding->id,
            'source_name' => $finding->source,
            'status' => 'ready_to_remove',
            'workflow_type' => $finding->removal_method === 'automated_request' ? 'automated' : 'manual',
            'estimated_turnaround' => $instruction?->typical_turnaround ?? ($finding->removal_method === 'automated_request' ? '3–14 days' : '1–7 days'),
            'opt_out_url' => $instruction?->direct_opt_out_link ?? $finding->url,
        ]);
    }

    protected function storeCredentialExposures(IdentityProfile $profile): int
    {
        if (! config('traceless.demo_mode')) {
            return 0;
        }

        $inserted = 0;
        foreach ($this->demoAdapter->credentialExposuresFor($profile) as $row) {
            $existing = CredentialExposure::where('identity_profile_id', $profile->id)
                ->where('service_name', $row['service_name'])
                ->first();

            if ($existing) {
                continue;
            }

            CredentialExposure::create([
                'identity_profile_id' => $profile->id,
                ...$row,
            ]);
            $inserted++;
        }

        return $inserted;
    }

    protected function setStage(Scan $scan, string $status, int $progress, string $stage, array $extra = []): void
    {
        $scan->update(array_merge([
            'status' => $status,
            'progress_percentage' => $progress,
            'current_stage' => $stage,
        ], $extra));

        event(new ScanProgressUpdated($scan->fresh()));
    }

    protected function wasCancelled(Scan $scan): bool
    {
        return $scan->fresh()->status === 'cancelled';
    }

    protected function delay(): void
    {
        $ms = (int) config('traceless.scan.stage_delay_ms');
        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }

    /**
     * A record that comes back needs work again. Reopen the existing task rather
     * than claiming a new one exists while leaving the old one closed.
     */
    protected function reopenOrCreateRemovalTask(IdentityProfile $profile, Finding $finding): void
    {
        $task = $finding->removalTask;

        if (! $task) {
            $this->ensureRemovalTask($profile, $finding);

            return;
        }

        $task->update([
            'status' => 'ready_to_remove',
            'completed_at' => null,
            'requested_at' => null,
            'notes' => trim(
                ($task->notes ? $task->notes."\n" : '')
                .'Reappeared on '.now()->toDateString().'; task reopened automatically.'
            ),
        ]);
    }

    protected function createReappearanceAlert(IdentityProfile $profile, Finding $finding): void
    {
        $this->createAlert(
            $profile,
            'reappeared',
            'high',
            'Removed information reappeared',
            "{$finding->source} is publishing your information again. Its removal task has been reopened.",
            route('findings.show', $finding)
        );
    }

    protected function emitMonitoringAlerts(IdentityProfile $profile, Scan $scan, int $newCount): void
    {
        if ($newCount < 1) {
            return;
        }

        if ($scan->type !== 'deep_scan' && ! $profile->monitoringSubscription?->is_active) {
            return;
        }

        $this->createAlert(
            $profile,
            'new_exposure',
            'moderate',
            'New public exposure detected',
            "{$newCount} new public record(s) were discovered during monitoring. Review the latest scan.",
            route('scans.show', $scan)
        );

        $profile->monitoringSubscription?->update([
            'last_scanned_at' => now(),
            'next_scan_at' => now()->addDays((int) config('traceless.monitoring.cadences.'.($profile->monitoringSubscription->cadence ?? 'weekly'), 7)),
        ]);
    }

    protected function createAlert(IdentityProfile $profile, string $type, string $severity, string $title, string $message, ?string $url = null): Alert
    {
        $alert = Alert::create([
            'identity_profile_id' => $profile->id,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'action_url' => $url,
            'is_read' => false,
        ]);

        event(new AlertCreated($alert));
        $profile->user->notify(new AlertNotification($alert));

        return $alert;
    }

    /**
     * Deadline for a deep scan only. Live snapshot scans keep their existing
     * bounds (identifier caps plus the 90s worker timeout).
     */
    protected function scanDeadline(Scan $scan): ?Carbon
    {
        $budget = (int) config('traceless.scan.deep_wall_clock_seconds', 480);

        if ($scan->type !== 'deep_scan' || $budget <= 0) {
            return null;
        }

        return now()->addSeconds($budget);
    }

    protected function guardScanDeadline(?Carbon $deadline): void
    {
        if ($deadline === null || now()->lessThan($deadline)) {
            return;
        }

        throw new ScanBudgetExceededException((int) config('traceless.scan.deep_wall_clock_seconds', 480));
    }

    /**
     * Cap one identifier subprocess so it cannot run past the deadline.
     */
    protected function remainingIdentifierTimeout(?Carbon $deadline): ?int
    {
        if ($deadline === null) {
            return null;
        }

        return max(1, (int) floor($deadline->getTimestamp() - now()->getTimestamp()));
    }
}
