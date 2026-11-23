<?php

namespace App\Services\IpIntelligence;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Persistent 48h cache plus the per-IP single-flight lock.
 *
 * The lock is a database row, not a cache entry: the app runs several PHP
 * processes (FPM pool, queue worker, scheduler) and cache stores can be flushed
 * independently. `locked_until` doubles as the in-flight marker the endpoint
 * reads, and the token prevents a late job from overwriting a newer analysis.
 */
class IpIntelligenceRepository
{
    private const TABLE = 'ip_intelligence_cache';

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $ip): ?array
    {
        $row = DB::table(self::TABLE)->where('ip', $ip)->first();

        return $row === null ? null : (array) $row;
    }

    /**
     * @param  array<string, mixed>|null  $row
     */
    public function isFresh(?array $row): bool
    {
        if ($row === null || $row['data'] === null || $row['expires_at'] === null) {
            return false;
        }

        return Carbon::parse($row['expires_at'])->isFuture();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    public function cachedData(array $row): ?array
    {
        if ($row['data'] === null) {
            return null;
        }

        $decoded = json_decode((string) $row['data'], true);

        return is_array($decoded) ? $decoded : null;
    }

    public function isInFlight(?array $row): bool
    {
        if ($row === null || $row['locked_until'] === null) {
            return false;
        }

        return Carbon::parse($row['locked_until'])->isFuture();
    }

    /**
     * Claim the analysis slot for one IP. Returns null when another request or
     * worker already holds it. This is the 20-visitors-one-analysis guarantee.
     */
    public function acquireLock(string $ip, int $ipVersion, int $lockSeconds): ?string
    {
        $now = now();

        DB::table(self::TABLE)->insertOrIgnore([
            'ip' => $ip,
            'ip_version' => $ipVersion,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $token = Str::random(48);

        $claimed = DB::table(self::TABLE)
            ->where('ip', $ip)
            ->where(function ($query) use ($now) {
                $query->whereNull('locked_until')->orWhere('locked_until', '<', $now);
            })
            ->update([
                'lock_token' => $token,
                'locked_until' => $now->copy()->addSeconds($lockSeconds),
                'updated_at' => $now,
            ]);

        return $claimed === 1 ? $token : null;
    }

    public function ownsLock(string $ip, string $token): bool
    {
        return DB::table(self::TABLE)
            ->where('ip', $ip)
            ->where('lock_token', $token)
            ->exists();
    }

    /**
     * Persist a completed analysis and release the lock.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, array<string, mixed>>  $providerResults
     * @param  array<string, string>  $providerStatus
     */
    public function complete(string $ip, string $token, array $data, array $providerResults, array $providerStatus, int $durationMs, Carbon $analyzedAt, Carbon $expiresAt): bool
    {
        $row = $this->find($ip);

        if ($row === null) {
            return false;
        }

        $updated = DB::table(self::TABLE)
            ->where('ip', $ip)
            ->where('lock_token', $token)
            ->update([
                'data' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'provider_results' => json_encode($providerResults, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'provider_status' => json_encode($providerStatus, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'risk_score' => $data['risk_score'] ?? null,
                'risk_grade' => $data['risk_grade'] ?? null,
                'risk_classification' => $data['risk_classification'] ?? null,
                'confidence' => $data['confidence'] ?? null,
                'analysis_ms' => $durationMs,
                'first_scanned_at' => $row['first_scanned_at'] ?? $analyzedAt,
                'last_scanned_at' => $analyzedAt,
                'expires_at' => $expiresAt,
                'lock_token' => null,
                'locked_until' => null,
                'updated_at' => now(),
            ]);

        return $updated === 1;
    }

    public function release(string $ip, string $token): void
    {
        DB::table(self::TABLE)
            ->where('ip', $ip)
            ->where('lock_token', $token)
            ->update(['lock_token' => null, 'locked_until' => null, 'updated_at' => now()]);
    }
}
