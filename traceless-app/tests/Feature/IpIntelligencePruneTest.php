<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IpIntelligencePruneTest extends TestCase
{
    use RefreshDatabase;

    private function cacheRow(string $ip, array $overrides = []): void
    {
        DB::table('ip_intelligence_cache')->insert(array_merge([
            'ip' => $ip,
            'ip_version' => 4,
            'data' => json_encode(['ip' => $ip]),
            'expires_at' => now()->subDays(3),
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(4),
        ], $overrides));
    }

    public function test_ip_prune_deletes_expired_rows_but_keeps_fresh_and_locked_ones(): void
    {
        $this->cacheRow('203.0.113.1');
        $this->cacheRow('203.0.113.2', [
            'expires_at' => now()->subHour(),
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);
        $this->cacheRow('203.0.113.3', ['expires_at' => now()->addHour()]);
        $this->cacheRow('203.0.113.4', [
            'expires_at' => now()->subDays(3),
            'locked_until' => now()->addMinutes(10),
        ]);
        $this->cacheRow('203.0.113.5', [
            'data' => null,
            'expires_at' => null,
        ]);

        $this->artisan('ip:prune')->assertExitCode(0);

        $this->assertDatabaseMissing('ip_intelligence_cache', ['ip' => '203.0.113.1']);
        $this->assertDatabaseHas('ip_intelligence_cache', ['ip' => '203.0.113.2']);
        $this->assertDatabaseHas('ip_intelligence_cache', ['ip' => '203.0.113.3']);
        $this->assertDatabaseHas('ip_intelligence_cache', ['ip' => '203.0.113.4']);
        $this->assertDatabaseMissing('ip_intelligence_cache', ['ip' => '203.0.113.5']);
    }

    public function test_ip_prune_dry_run_deletes_nothing(): void
    {
        $this->cacheRow('203.0.113.10');

        $this->artisan('ip:prune', ['--dry-run' => true])->assertExitCode(0);

        $this->assertDatabaseHas('ip_intelligence_cache', ['ip' => '203.0.113.10']);
    }

    public function test_ip_prune_leaves_operational_counters_alone(): void
    {
        DB::table('ip_intelligence_metrics')->insert([
            'metric' => 'analysis.completed',
            'value' => 7,
            'created_at' => now()->subDays(30),
            'updated_at' => now()->subDays(30),
        ]);

        $this->cacheRow('203.0.113.20');

        $this->artisan('ip:prune')->assertExitCode(0);

        $this->assertDatabaseHas('ip_intelligence_metrics', ['metric' => 'analysis.completed', 'value' => 7]);
    }

    public function test_ip_prune_respects_a_custom_grace_window(): void
    {
        $this->cacheRow('203.0.113.30', [
            'expires_at' => now()->subHours(2),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $this->artisan('ip:prune', ['--hours' => 1])->assertExitCode(0);

        $this->assertDatabaseMissing('ip_intelligence_cache', ['ip' => '203.0.113.30']);
    }
}
