<?php

namespace App\Jobs;

use App\Models\MonitoringSubscription;
use App\Models\Scan;
use App\Services\ConsentService;
use App\Services\ScanOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled monitoring runner. Creates a queued scan for every active
 * subscription whose next_scan_at has elapsed.
 */
class DispatchDueMonitoringScans implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ScanOrchestrator $orchestrator): void
    {
        MonitoringSubscription::with('identityProfile.user')
            ->where('is_active', true)
            ->whereNotNull('next_scan_at')
            ->where('next_scan_at', '<=', now())
            ->chunkById(50, function ($subscriptions) use ($orchestrator) {
                foreach ($subscriptions as $subscription) {
                    $profile = $subscription->identityProfile;
                    if (! $profile || ! $orchestratorCanScan($profile)) {
                        continue;
                    }

                    try {
                        $orchestrator->queueScan($profile, 'deep_scan');
                        $subscription->update([
                            'last_scanned_at' => now(),
                            'next_scan_at' => now()->addDays(match ($subscription->cadence) {
                                'daily' => 1,
                                'monthly' => 30,
                                default => 7,
                            }),
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('monitoring_scan_skipped', [
                            'subscription_id' => $subscription->id,
                            'reason' => get_class($e),
                        ]);
                    }
                }
            });
    }

    protected function orchestratorCanScan($profile): bool
    {
        return app(ConsentService::class)->hasValidConsent($profile);
    }
}
