<?php

namespace App\Services\IpIntelligence;

use App\Jobs\AnalyzeIpIntelligence;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Public entry point for the landing-page IP lookup.
 *
 * lookup() never calls an external provider: a cache hit returns the stored
 * record, a miss returns "warming" and hands one job to the queue. The database
 * lock in IpIntelligenceRepository guarantees a single dispatch per IP, even
 * when twenty visitors arrive at the same time.
 */
class IpIntelligenceService
{
    public function __construct(
        private IpIntelligenceRepository $repository,
        private ProviderRunner $runner,
        private IpIntelligenceReportBuilder $builder,
        private IpIntelligenceMetrics $metrics,
    ) {}

    /**
     * @return array{status: string, data: array<string, mixed>|null}
     */
    public function lookup(string $normalizedIp): array
    {
        $row = $this->repository->find($normalizedIp);

        if ($this->repository->isFresh($row)) {
            $cached = $this->repository->cachedData($row);

            if ($cached !== null) {
                $this->metrics->recordCacheHit();

                return ['status' => 'ready', 'data' => $cached];
            }
        }

        $token = $this->repository->acquireLock(
            $normalizedIp,
            IpAddressNormalizer::version($normalizedIp),
            max(30, (int) config('traceless.ip_intelligence.lock_seconds', 120)),
        );

        if ($token === null) {
            // Either a worker is analyzing this IP right now, or it finished
            // between the cache read and the lock attempt. Re-read once.
            $row = $this->repository->find($normalizedIp);
            $cached = $this->repository->isFresh($row) ? $this->repository->cachedData($row) : null;

            if ($cached !== null) {
                $this->metrics->recordCacheHit();

                return ['status' => 'ready', 'data' => $cached];
            }

            $this->metrics->recordCacheMiss();

            return ['status' => 'warming', 'data' => null];
        }

        $this->metrics->recordCacheMiss();

        try {
            AnalyzeIpIntelligence::dispatch($normalizedIp, $token)->onQueue('default');
        } catch (Throwable $exception) {
            $this->repository->release($normalizedIp, $token);
            $this->metrics->recordDispatchFailed();

            Log::warning('ip_intelligence_dispatch_failed', [
                'ip_hash' => hash('sha256', $normalizedIp),
                'reason' => get_class($exception),
            ]);
        }

        return ['status' => 'warming', 'data' => null];
    }

    /**
     * Runs inside the queue worker. Returns false when the job lost the lock,
     * which is the expected outcome for a duplicated or late delivery.
     */
    public function analyze(string $ip, string $token): bool
    {
        if (! $this->repository->ownsLock($ip, $token)) {
            return false;
        }

        $startedAt = microtime(true);
        $results = $this->runner->gather($ip);

        $analyzedAt = now();
        $expiresAt = $analyzedAt->copy()->addSeconds($this->cacheTtl($results));

        $data = $this->builder->build($ip, IpAddressNormalizer::version($ip), $results, $analyzedAt, $expiresAt);
        $providerResults = [];
        $providerStatus = [];

        foreach ($results as $name => $result) {
            $providerResults[$name] = $result->toStoredArray();
            $providerStatus[$name] = $result->status;
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        $stored = $this->repository->complete(
            $ip,
            $token,
            $data,
            $providerResults,
            $providerStatus,
            $durationMs,
            $analyzedAt,
            $expiresAt,
        );

        if ($stored) {
            $this->metrics->recordAnalysis($durationMs);
        }

        return $stored;
    }

    /**
     * Full window only when every provider answered; degraded coverage expires
     * sooner so an outage does not freeze a stale record for two days.
     *
     * @param  array<string, ProviderResult>  $results
     */
    private function cacheTtl(array $results): int
    {
        $full = max(60, (int) config('traceless.ip_intelligence.cache_ttl_seconds', 172800));

        if ($results === []) {
            return max(60, (int) config('traceless.ip_intelligence.minimal_cache_ttl_seconds', 600));
        }

        $ok = count(array_filter($results, fn (ProviderResult $result) => $result->isOk()));

        return match (true) {
            $ok === count($results) => $full,
            $ok >= 2 => max(60, min($full, (int) config('traceless.ip_intelligence.degraded_cache_ttl_seconds', 21600))),
            default => max(60, min($full, (int) config('traceless.ip_intelligence.minimal_cache_ttl_seconds', 600))),
        };
    }
}
