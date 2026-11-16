<?php

namespace Tests\Unit;

use App\Support\DestructiveCommandGuard;
use PHPUnit\Framework\TestCase;

class DestructiveCommandGuardTest extends TestCase
{
    private const NEON_PRODUCTION = 'postgresql://user:pass@ep-x-pooler.eu-central-1.aws.neon.tech/neondb?sslmode=require';

    public function test_it_blocks_destructive_commands_on_the_production_branch(): void
    {
        foreach (['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'db:wipe'] as $command) {
            $this->assertTrue(
                DestructiveCommandGuard::blocks($command, self::NEON_PRODUCTION, 'production', false),
                "{$command} should be blocked"
            );
        }
    }

    public function test_it_allows_additive_commands(): void
    {
        foreach (['migrate', 'migrate:status', 'db:seed', 'config:clear'] as $command) {
            $this->assertFalse(
                DestructiveCommandGuard::blocks($command, self::NEON_PRODUCTION, 'production', false),
                "{$command} should be allowed"
            );
        }
    }

    public function test_it_allows_destructive_commands_away_from_neon_production(): void
    {
        $this->assertFalse(
            DestructiveCommandGuard::blocks('db:wipe', 'sqlite', 'production', false)
        );
        $this->assertFalse(
            DestructiveCommandGuard::blocks('migrate:fresh', self::NEON_PRODUCTION, 'development', false)
        );
        $this->assertFalse(
            DestructiveCommandGuard::blocks('migrate:fresh', self::NEON_PRODUCTION, '', false)
        );
    }

    public function test_an_explicit_override_disables_the_guard(): void
    {
        $this->assertFalse(
            DestructiveCommandGuard::blocks('migrate:fresh', self::NEON_PRODUCTION, 'production', true)
        );
    }
}
