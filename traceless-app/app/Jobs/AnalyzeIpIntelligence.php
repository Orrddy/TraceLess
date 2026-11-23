<?php

namespace App\Jobs;

use App\Services\IpIntelligence\IpIntelligenceMetrics;
use App\Services\IpIntelligence\IpIntelligenceRepository;
use App\Services\IpIntelligence\IpIntelligenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Background analysis for one visitor IP.
 *
 * The job carries the lock token acquired by the request that dispatched it; a
 * late or duplicated delivery can therefore tell that its slot was taken over
 * and skip without calling any provider.
 */
class AnalyzeIpIntelligence implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * One attempt only: a failed analysis is retried by the next landing-page
     * request, which is friendlier to provider quotas than queue-level retries.
     */
    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public string $ip,
        public string $token,
    ) {}

    public function handle(IpIntelligenceService $service): void
    {
        $service->analyze($this->ip, $this->token);
    }

    public function failed(?Throwable $exception): void
    {
        app(IpIntelligenceRepository::class)->release($this->ip, $this->token);
        app(IpIntelligenceMetrics::class)->recordJobFailed();

        Log::warning('ip_intelligence_job_failed', [
            'ip_hash' => hash('sha256', $this->ip),
            'exception' => $exception === null ? 'unknown' : get_class($exception),
        ]);
    }
}
