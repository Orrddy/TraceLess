<?php

namespace App\Support;

use Illuminate\Console\Events\CommandStarting;
use RuntimeException;

/**
 * Refuses database-destroying artisan commands when the connection points at the
 * Neon production branch.
 *
 * Local development currently reuses that branch, which means one `migrate:fresh`
 * or `db:wipe` would erase real data. The durable fix is a separate development
 * branch; until that exists, this guard makes the dangerous commands fail loudly
 * instead of silently.
 *
 * A deliberate run is still possible: ALLOW_DESTRUCTIVE_DB=1.
 */
class DestructiveCommandGuard
{
    /** Commands that destroy data rather than add to it. */
    private const BLOCKED = [
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'db:wipe',
    ];

    public function handle(CommandStarting $event): void
    {
        $command = (string) $event->command;

        if (! self::blocks(
            $command,
            (string) config('database.connections.'.config('database.default').'.url'),
            (string) env('NEON_BRANCH', ''),
            (bool) env('ALLOW_DESTRUCTIVE_DB', false),
        )) {
            return;
        }

        throw new RuntimeException(
            "Refusing to run `{$command}`: the database connection points at the Neon production branch "
            .'(NEON_BRANCH=production). Point DB_URL at a development branch, or set ALLOW_DESTRUCTIVE_DB=1 '
            .'if you really intend to destroy production data.'
        );
    }

    /**
     * Pure rule so the decision can be tested without touching a database.
     */
    public static function blocks(string $command, string $databaseUrl, string $neonBranch, bool $override): bool
    {
        if ($override) {
            return false;
        }

        if (! in_array($command, self::BLOCKED, true)) {
            return false;
        }

        $isNeon = str_contains(strtolower($databaseUrl), 'neon.tech');
        $isProductionBranch = strtolower(trim($neonBranch)) === 'production';

        return $isNeon && $isProductionBranch;
    }
}
