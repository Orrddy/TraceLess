<?php

namespace App\Jobs;

use App\Models\MonitoringSubscription;
use App\Services\ConsentService;
use App\Services\ScanOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Scheduled monitoring runner. Creates a queued scan for every active
 * subscription whose next_scan_at has elapsed.
 *
 * Three failure modes are handled explicitly:
 * - a subscription with no next_scan_at at all is treated as due now;
 * - a profile without valid consent, or without the required plan, is pushed
 *   forward instead of being retried every hour forever;
 * - a dispatch failure backs off for six hours rather than hammering.
 */
class DispatchDueMonitoringScans implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ScanOrchestrator $orchestrator): void
    {
        $enforcePlan = (bool) config('traceless.monitoring.enforce_plan', false);

        MonitoringSubscription::with('identityProfile.user')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('next_scan_at')->orWhere('next_scan_at', '<=', now());
            })
            ->chunkById(50, function ($subscriptions) use ($orchestrator, $enforcePlan) {
                foreach ($subscriptions as $subscription) {
                    $profile = $subscription->identityProfile;

                    if (! $profile) {
                        continue;
                    }

                    if (! app(ConsentService::class)->hasValidConsent($profile)) {
                        Log::info('monitoring_skipped_no_consent', ['subscription_id' => $subscription->id]);
                        $subscription->update(['next_scan_at' => now()->addHours(6)]);

                        continue;
                    }

                    // Plan gate through the user model, which delegates to
                    // App\Support\BetaAccess: the free beta runs for everyone
                    // active, v2.0 restores the Deep Scan subscription check.
                    if ($enforcePlan && ! $profile->user?->isDeepScan()) {
                        Log::info('monitoring_skipped_no_plan', ['subscription_id' => $subscription->id]);
                        $subscription->update(['next_scan_at' => now()->addDay()]);

                        continue;
                    }

                    // The owner may be running a manual scan; queueing a second
                    // one would double the work and duplicate alerts.
                    if ($profile->activeScan()) {
                        Log::info('monitoring_skipped_active_scan', ['subscription_id' => $subscription->id]);
                        $subscription->update(['next_scan_at' => now()->addHour()]);

                        continue;
                    }

                    try {
                        $orchestrator->queueScan($profile, 'deep_scan');

                        $subscription->update([
                            'last_scanned_at' => now(),
                            'next_scan_at' => now()->addDays($this->cadenceDays($subscription->cadence)),
                        ]);
                    } catch (Throwable $e) {
                        Log::warning('monitoring_dispatch_failed', [
                            'subscription_id' => $subscription->id,
                            'reason' => get_class($e),
                        ]);

                        // Do not retry every hour against a permanent failure.
                        $subscription->update(['next_scan_at' => now()->addHours(6)]);
                    }
                }
            });
    }

    private function cadenceDays(?string $cadence): int
    {
        return match ($cadence) {
            'daily' => 1,
            'monthly' => 30,
            default => 7,
        };
    }
}
