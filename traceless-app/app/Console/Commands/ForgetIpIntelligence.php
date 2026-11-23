<?php

namespace App\Console\Commands;

use App\Services\IpIntelligence\IpAddressNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Operator escape hatch for cached IP intelligence.
 *
 * Deleting a row makes the next visit queue a fresh analysis. This is the tool
 * for the zero-coverage rows written before provider keys existed; it is not
 * scheduled, and normal retention is handled by ip:prune.
 */
class ForgetIpIntelligence extends Command
{
    protected $signature = 'ip:forget
                            {ip? : Public IPv4 or IPv6 address to forget}
                            {--all : Forget every cached IP address}
                            {--dry-run : Show what would be deleted without deleting it}';

    protected $description = 'Delete cached IP intelligence rows so the next visit re-analyses them.';

    public function handle(): int
    {
        $ip = $this->argument('ip');
        $all = (bool) $this->option('all');

        if ($ip === null && ! $all) {
            $this->error('Provide an IP address or pass --all.');

            return self::FAILURE;
        }

        if ($ip !== null && $all) {
            $this->error('Use either an IP address or --all, not both.');

            return self::FAILURE;
        }

        $query = DB::table('ip_intelligence_cache');
        $scope = 'all';

        if ($ip !== null) {
            $normalized = IpAddressNormalizer::normalize((string) $ip);

            if ($normalized === null) {
                $this->error('That is not a valid public IPv4 or IPv6 address.');

                return self::FAILURE;
            }

            $query->where('ip', $normalized);
            $scope = $normalized;
        }

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->table(['Cached IP rows', 'Scope'], [[$count, $scope]]);

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->info("Deleted {$deleted} cached IP intelligence row(s) for {$scope}.");

        return self::SUCCESS;
    }
}
