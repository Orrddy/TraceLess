<?php

namespace App\Console\Commands;

use App\Models\Scan;
use Illuminate\Console\Command;

/**
 * Fails scans that stopped making progress.
 *
 * A queued job can disappear without ever running (worker killed, container
 * recycled, deploy), and Laravel never calls failed() in that case. This command
 * is the safety net: anything non-terminal that has not been touched for the
 * configured window is closed, so the account can scan again.
 */
class ReapStaleScans extends Command
{
    protected $signature = 'scans:reap-stale {--minutes= : Override the stale window} {--dry-run}';

    protected $description = 'Fail scans that have made no progress within the stale window.';

    public function handle(): int
    {
        $minutes = (int) ($this->option('minutes') ?: config('traceless.scan.stale_minutes', 15));

        $stale = Scan::query()
            ->whereNotIn('status', ['completed', 'failed', 'cancelled'])
            ->where('updated_at', '<', now()->subMinutes($minutes));

        $count = (clone $stale)->count();

        if ($this->option('dry-run')) {
            $this->table(['Stale scans', 'Window (minutes)'], [[$count, $minutes]]);

            return self::SUCCESS;
        }

        $closed = 0;

        foreach ((clone $stale)->get() as $scan) {
            $scan->update([
                'status' => 'failed',
                'current_stage' => 'Scan stalled and was closed automatically. You can start a new one.',
                'error_message' => 'The scan made no progress and was closed after '.$minutes.' minutes. Please retry.',
                'completed_at' => now(),
            ]);

            $closed++;
        }

        $this->info("Closed {$closed} stale scan(s) with a {$minutes} minute window.");

        return self::SUCCESS;
    }
}
