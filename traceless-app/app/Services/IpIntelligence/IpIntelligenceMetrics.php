<?php

namespace App\Services\IpIntelligence;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Operational counters for the IP intelligence engine.
 *
 * Counters are monotonic rows in `ip_intelligence_metrics` because web requests
 * and queue workers update them from different processes. The admin page should
 * read them through snapshot(); the raw metric keys are:
 *
 *  cache.hit, cache.miss, analysis.completed, analysis.duration_ms,
 *  job.failed, job.dispatch_failed,
 *  provider.{provider}.call, provider.{provider}.error,
 *  provider.{provider}.timeout, provider.{provider}.rate_limited,
 *  provider.{provider}.unavailable
 *
 * where {provider} is one of ipapi_is, proxycheck, abuseipdb, ip2location.
 */
class IpIntelligenceMetrics
{
    public const CACHE_HIT = 'cache.hit';

    public const CACHE_MISS = 'cache.miss';

    public const ANALYSIS_COMPLETED = 'analysis.completed';

    public const ANALYSIS_DURATION_MS = 'analysis.duration_ms';

    public const JOB_FAILED = 'job.failed';

    public const JOB_DISPATCH_FAILED = 'job.dispatch_failed';

    private const TABLE = 'ip_intelligence_metrics';

    public function recordCacheHit(): void
    {
        $this->increment(self::CACHE_HIT);
    }

    public function recordCacheMiss(): void
    {
        $this->increment(self::CACHE_MISS);
    }

    public function recordAnalysis(int $durationMs): void
    {
        $this->increment(self::ANALYSIS_COMPLETED);
        $this->increment(self::ANALYSIS_DURATION_MS, max(0, $durationMs));
    }

    public function recordJobFailed(): void
    {
        $this->increment(self::JOB_FAILED);
    }

    public function recordDispatchFailed(): void
    {
        $this->increment(self::JOB_DISPATCH_FAILED);
    }

    public function recordProviderCall(string $provider): void
    {
        $this->increment('provider.'.$provider.'.call');
    }

    public function recordProviderError(string $provider): void
    {
        $this->increment('provider.'.$provider.'.error');
    }

    public function recordProviderTimeout(string $provider): void
    {
        $this->increment('provider.'.$provider.'.timeout');
    }

    public function recordRateLimited(string $provider): void
    {
        $this->increment('provider.'.$provider.'.rate_limited');
    }

    public function recordProviderUnavailable(string $provider): void
    {
        $this->increment('provider.'.$provider.'.unavailable');
    }

    /**
     * @return array<string, int>
     */
    public function counters(): array
    {
        return DB::table(self::TABLE)->pluck('value', 'metric')->map(fn ($value) => (int) $value)->all();
    }

    /**
     * Everything an admin panel needs, with zeros for missing counters.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $counters = $this->counters();
        $providers = array_keys((array) config('traceless.ip_intelligence.providers', []));

        $pick = function (string $key, int $default = 0) use ($counters): int {
            return $counters[$key] ?? $default;
        };

        $perProvider = function (string $suffix) use ($providers, $counters): array {
            $values = [];

            foreach ($providers as $provider) {
                $values[$provider] = (int) ($counters['provider.'.$provider.'.'.$suffix] ?? 0);
            }

            return $values;
        };

        $analyses = $pick(self::ANALYSIS_COMPLETED);
        $durationTotal = $pick(self::ANALYSIS_DURATION_MS);

        return [
            'total_analyzed' => $analyses,
            'fresh_scans' => $analyses,
            'cache_hits' => $pick(self::CACHE_HIT),
            'cache_misses' => $pick(self::CACHE_MISS),
            'cached_ips' => DB::table('ip_intelligence_cache')->where('expires_at', '>', now())->count(),
            'expired_ips' => DB::table('ip_intelligence_cache')->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '<=', now()))->count(),
            'in_flight' => DB::table('ip_intelligence_cache')->where('locked_until', '>', now())->count(),
            'provider_calls' => $perProvider('call'),
            'provider_errors' => $perProvider('error'),
            'provider_timeouts' => $perProvider('timeout'),
            'rate_limit_events' => $perProvider('rate_limited'),
            'provider_unavailable' => $perProvider('unavailable'),
            'average_analysis_ms' => $analyses > 0 ? (int) round($durationTotal / $analyses) : null,
            'failed_jobs' => $pick(self::JOB_FAILED),
            'dispatch_failures' => $pick(self::JOB_DISPATCH_FAILED),
        ];
    }

    private function increment(string $metric, int $amount = 1): void
    {
        $amount = max(0, $amount);
        $now = now();

        $updated = DB::table(self::TABLE)
            ->where('metric', $metric)
            ->update(['value' => DB::raw('value + '.$amount), 'updated_at' => $now]);

        if ($updated > 0) {
            return;
        }

        try {
            DB::table(self::TABLE)->insert([
                'metric' => $metric,
                'value' => $amount,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (Throwable) {
            // Another process inserted the row between the update and the insert.
            DB::table(self::TABLE)
                ->where('metric', $metric)
                ->update(['value' => DB::raw('value + '.$amount), 'updated_at' => $now]);
        }
    }
}
