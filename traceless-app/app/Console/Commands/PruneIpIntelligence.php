<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deletes IP intelligence cache rows that are past their usefulness.
 *
 * A row is removable when its analysis has expired (or it never completed) and
 * no worker currently holds the lock. The grace window keeps recently expired
 * rows around long enough that an in-flight re-analysis is never deleted from
 * under the job that is about to write it.
 */
class PruneIpIntelligence extends Command
{
    protected $signature = 'ip:prune {--hours= : Grace period past expiry, in hours} {--dry-run}';

    protected $description = 'Delete expired IP intelligence cache rows.';

    public function handle(): int
    {
        $hours = max(0, (int) ($this->option('hours') ?? config('traceless.ip_intelligence.prune_after_hours', 24)));
        $cutoff = now()->subHours($hours);
        $now = now();

        $stale = DB::table('ip_intelligence_cache')
            ->where(function ($query) use ($cutoff) {
                $query->where(function ($expired) use ($cutoff) {
                    $expired->whereNotNull('expires_at')->where('expires_at', '<=', $cutoff);
                })->orWhere(function ($neverFinished) use ($cutoff) {
                    // Rows created by a request whose job never completed.
                    $neverFinished->whereNull('expires_at')->where('created_at', '<=', $cutoff);
                });
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('locked_until')->orWhere('locked_until', '<=', $now);
            });

        $count = (clone $stale)->count();

        if ($this->option('dry-run')) {
            $this->table(['Expired IP intelligence rows', 'Grace (hours)'], [[$count, $hours]]);

            return self::SUCCESS;
        }

        $deleted = $stale->delete();

        $this->info("Deleted {$deleted} expired IP intelligence row(s) with a {$hours}h grace window.");

        return self::SUCCESS;
    }
}
