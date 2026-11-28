<?php

namespace App\Livewire;

use App\Models\QuickScanRecord;
use App\Services\PythonScannerClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Throwable;

/**
 * A deliberately small, guest-only check.
 *
 * It runs ten curated public-profile checks for one username. Nothing is
 * persisted: the name, email and username live only in this Livewire component
 * (and the name/email are put in the session only to prefill signup). A full
 * scan still requires an account and recorded profile consent.
 */
class QuickScan extends Component
{
    public string $name = '';

    public string $email = '';

    public string $username = '';

    public bool $consent = false;

    /** Honeypot: hidden from humans, filled by basic bots. */
    public string $website = '';

    public bool $scanned = false;

    public string $scanError = '';

    /** @var array<string, mixed>|null */
    public ?array $results = null;

    /** Quick sources that expose an email-registration probe. */
    private const QUICK_EMAIL_SOURCES = [
        'Facebook',
        'Instagram',
        'X',
        'Pinterest',
    ];

    /** @var list<string> */
    public array $checks = [
        'Facebook',
        'Instagram',
        'X',
        'LinkedIn',
        'TikTok',
        'YouTube',
        'Reddit',
        'Telegram',
        'Snapchat',
        'Pinterest',
    ];

    public function scan(PythonScannerClient $scanner): void
    {
        @set_time_limit(120);
        $this->resetErrorBag();
        $this->scanError = '';
        $this->scanned = false;
        $this->results = null;

        if ($this->website !== '') {
            $this->scanError = 'The quick scan could not run. Please refresh and try again.';

            return;
        }

        $this->name = trim(strip_tags($this->name));
        $this->email = trim($this->email);
        $this->username = ltrim(trim($this->username), '@');

        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'username' => ['required', 'string', 'min:2', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'consent' => ['accepted'],
        ], [
            'consent.accepted' => 'Confirm this is your public username and email before running the check.',
            'username.regex' => 'Use a public username (letters, numbers, dots, dashes and underscores only).',
        ]);

        $rateKey = 'quick-scan:'.request()->ip();

        if (RateLimiter::tooManyAttempts($rateKey, 2)) {
            $this->addError('username', 'Please wait a few minutes before running another quick check.');

            return;
        }

        RateLimiter::hit($rateKey, 600);

        try {
            $report = $scanner->scanReport(
                'username',
                $this->username,
                true,
                'quick',
                45,
            );
        } catch (Throwable $exception) {
            Log::warning('guest_quick_scan_failed', [
                'reason' => get_class($exception),
            ]);

            $this->scanError = 'The quick scan could not reach its public sources just now. Please try again in a minute, or create a free account for the full scan.';

            return;
        }

        $findings = collect($report['findings'])->keyBy('source');

        $sources = collect($report['sources'])
            ->map(function (array $source) use ($findings): array {
                $finding = $findings->get($source['name']);
                $outcome = in_array($source['outcome'] ?? null, ['found', 'not_found', 'unknown', 'skipped'], true)
                    ? $source['outcome']
                    : 'unknown';

                return [
                    'name' => (string) $source['name'],
                    'outcome' => $outcome,
                    'reason' => $source['reason'] ?? null,
                    'url' => $finding['url'] ?? ($source['checked_url'] ?? null),
                    'title' => $finding['title'] ?? null,
                ];
            })
            ->values()
            ->all();

        $publicMatches = collect($sources)->where('outcome', 'found')->count();

        // Email is checked against the same short list, using only the eight
        // sources that expose an email-registration probe. A failure here must
        // not hide the username results.
        $emailSources = collect();
        $emailScanError = '';
        try {
            $emailReport = $scanner->scanReport('email', $this->email, true, 'quick', 45);
            $emailSources = collect($emailReport['sources'] ?? [])
                ->keyBy(fn (array $source): string => strtolower((string) ($source['name'] ?? '')));
        } catch (Throwable $exception) {
            Log::warning('guest_quick_scan_email_failed', [
                'reason' => get_class($exception),
            ]);
            $emailScanError = 'Email registration checks could not reach every source just now.';
        }

        $sources = collect($sources)->map(function (array $source) use ($emailSources, $emailScanError): array {
            $name = (string) $source['name'];
            $emailSupported = in_array($name, self::QUICK_EMAIL_SOURCES, true);
            $email = $emailSources->get(strtolower($name));

            if (is_array($email)) {
                $emailOutcome = $email['outcome'] ?? 'unknown';
            } elseif ($emailSupported && $emailScanError !== '') {
                $emailOutcome = 'unknown';
            } else {
                $emailOutcome = 'unsupported';
            }

            return $source + [
                'email_outcome' => $emailOutcome,
                'email_url' => is_array($email) ? ($email['checked_url'] ?? null) : null,
                'email_reason' => is_array($email) ? ($email['reason'] ?? null) : null,
                'email_supported' => $emailSupported,
            ];
        })->values()->all();

        $emailMatches = collect($sources)->where('email_outcome', 'found')->count();
        $checked = count($sources);

        $this->results = [
            'username' => $this->username,
            'email' => $this->email,
            'sources' => $sources,
            'public_matches' => $publicMatches + $emailMatches,
            'username_matches' => $publicMatches,
            'email_matches' => $emailMatches,
            'checked' => $checked,
            'unverified' => collect($sources)->whereIn('outcome', ['unknown', 'skipped'])->count(),
            'email_unverified' => collect($sources)->whereIn('email_outcome', ['unknown', 'skipped'])->count(),
            'email_supported' => collect($sources)->where('email_supported', true)->count(),
            'email_scan_error' => $emailScanError,
            'scanned_at' => now()->format('H:i'),
        ];
        $this->scanned = true;
        $this->dispatch('quick-scan-complete', publicMatches: $publicMatches);

        $greenSources = collect($sources)
            ->filter(fn (array $source): bool => $source['outcome'] === 'found' || $source['email_outcome'] === 'found')
            ->pluck('name')
            ->unique()
            ->values()
            ->all();

        // Admin-only audit trail. The results are never exposed publicly and
        // nothing is attached to a user account.
        try {
            QuickScanRecord::create([
                'name' => $this->name,
                'email' => $this->email,
                'username' => $this->username,
                'sources' => $sources,
                'green_sources' => $greenSources,
                'checked_count' => $checked,
                'public_matches' => $publicMatches + $emailMatches,
                'username_matches' => $publicMatches,
                'email_matches' => $emailMatches,
                'ip_hash' => hash_hmac('sha256', (string) request()->ip(), (string) config('app.key')),
            ]);
        } catch (Throwable $exception) {
            Log::warning('quick_scan_audit_failed', [
                'reason' => get_class($exception),
            ]);
        }

        // Purposeful use of the optional details: the auth pages may prefill.
        session()->put('quick_scan_prefill', [
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
        ]);
    }

    public function resetScan(): void
    {
        $this->dispatch('quick-scan-reset');
        $this->scanned = false;
        $this->results = null;
        $this->scanError = '';
        $this->username = '';
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.quick-scan')
            ->layout('layouts.guest-scan', ['title' => 'Free quick scan — TraceLess']);
    }
}
