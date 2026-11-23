<?php

namespace Tests\Feature;

use App\Models\IdentityProfile;
use App\Models\Report;
use App\Models\Scan;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\PythonScannerClient;
use App\Services\ScanOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ScanBudgetTest extends TestCase
{
    use RefreshDatabase;

    private function profile(): IdentityProfile
    {
        $user = User::factory()->create();

        $profile = IdentityProfile::create([
            'user_id' => $user->id,
            'full_name' => 'Budget Tester',
            'primary_email' => $user->email,
            'consent_acknowledged' => true,
            'consent_given_at' => now(),
        ]);

        app(ConsentService::class)->recordConsent($user, $profile, '127.0.0.1');

        $profile->publicIdentifiers()->create(['type' => 'username', 'value' => 'budgetuser', 'is_monitored' => true]);
        $profile->publicIdentifiers()->create(['type' => 'email', 'value' => 'budget@example.com', 'is_monitored' => true]);
        $profile->publicIdentifiers()->create(['type' => 'phone', 'value' => '+12025550143', 'is_monitored' => true]);

        return $profile;
    }

    private function report(): array
    {
        return [
            'findings' => [],
            'sources' => [
                ['name' => 'GitHub', 'category' => 'developer_profile', 'outcome' => 'not_found'],
            ],
        ];
    }

    public function test_a_deep_scan_that_exceeds_its_budget_fails_cleanly(): void
    {
        Queue::fake();
        config([
            'traceless.demo_mode' => false,
            'traceless.scan.stage_delay_ms' => 0,
            'traceless.scan.deep_wall_clock_seconds' => 120,
        ]);

        $profile = $this->profile();

        $mock = Mockery::mock(PythonScannerClient::class);
        $mock->shouldReceive('scanReport')
            ->once()
            ->andReturnUsing(function () {
                // One identifier alone eats more than the 120s budget.
                $this->travel(3)->minutes();

                return $this->report();
            });
        $this->app->instance(PythonScannerClient::class, $mock);

        $orchestrator = app(ScanOrchestrator::class);
        $scan = $orchestrator->queueScan($profile, 'deep_scan');
        $orchestrator->executeScan($scan);

        $scan->refresh();

        $this->assertSame('failed', $scan->status);
        $this->assertNotNull($scan->completed_at);
        $this->assertStringContainsString('120 second wall-clock budget', (string) $scan->error_message);
        $this->assertStringContainsString('No report was generated', (string) $scan->error_message);
        $this->assertSame('Deep scan stopped at its wall-clock budget.', $scan->current_stage);
        $this->assertSame(0, Report::count());
        $this->assertSame(0, $scan->findings()->count());
    }

    public function test_the_budget_does_not_apply_to_snapshot_scans(): void
    {
        Queue::fake();
        config([
            'traceless.demo_mode' => false,
            'traceless.scan.stage_delay_ms' => 0,
            'traceless.scan.deep_wall_clock_seconds' => 1,
        ]);

        $profile = $this->profile();

        $mock = Mockery::mock(PythonScannerClient::class);
        $mock->shouldReceive('scanReport')
            ->once()
            ->with('username', 'budgetuser', true, 'curated')
            ->andReturnUsing(function () {
                $this->travel(5)->minutes();

                return $this->report();
            });
        $this->app->instance(PythonScannerClient::class, $mock);

        $orchestrator = app(ScanOrchestrator::class);
        $scan = $orchestrator->queueScan($profile, 'snapshot');
        $orchestrator->executeScan($scan);

        $this->assertSame('completed', $scan->fresh()->status);
    }

    public function test_a_deep_scan_inside_its_budget_completes_normally(): void
    {
        Queue::fake();
        config([
            'traceless.demo_mode' => false,
            'traceless.scan.stage_delay_ms' => 0,
            'traceless.scan.deep_wall_clock_seconds' => 480,
        ]);

        $profile = $this->profile();

        $mock = Mockery::mock(PythonScannerClient::class);
        $mock->shouldReceive('scanReport')->andReturn($this->report());
        $this->app->instance(PythonScannerClient::class, $mock);

        $orchestrator = app(ScanOrchestrator::class);
        $scan = $orchestrator->queueScan($profile, 'deep_scan');
        $orchestrator->executeScan($scan);

        $scan->refresh();

        $this->assertSame('completed', $scan->status);
        $this->assertSame(1, Scan::count());
        $this->assertSame(1, Report::count());
    }
}
