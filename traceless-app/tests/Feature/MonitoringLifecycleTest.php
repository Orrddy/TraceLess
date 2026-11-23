<?php

namespace Tests\Feature;

use App\Jobs\DispatchDueMonitoringScans;
use App\Models\Alert;
use App\Models\Finding;
use App\Models\IdentityProfile;
use App\Models\MonitoringSubscription;
use App\Models\RemovalTask;
use App\Models\Scan;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\PythonScannerClient;
use App\Services\ScanOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class MonitoringLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function profile(bool $withConsent = true): IdentityProfile
    {
        $user = User::factory()->create();

        $profile = IdentityProfile::create([
            'user_id' => $user->id,
            'full_name' => 'Jane Doe',
            'primary_email' => 'jane@example.com',
            'consent_acknowledged' => $withConsent,
            'consent_given_at' => $withConsent ? now() : null,
        ]);

        if ($withConsent) {
            app(ConsentService::class)->recordConsent($user, $profile, '127.0.0.1');
        }

        // Live scans check usernames; without one the pipeline has nothing to do.
        $profile->publicIdentifiers()->create([
            'type' => 'username',
            'value' => 'janedoe',
            'is_monitored' => true,
        ]);

        return $profile;
    }

    private function report(string $source = 'GitHub', string $identifier = 'janedoe'): array
    {
        return [
            'findings' => [[
                'source' => $source,
                'category' => 'developer_profile',
                'identifier_type' => 'username',
                'identifier_value' => $identifier,
                'url' => "https://{$source}.test/{$identifier}",
                'confidence' => 0.9,
                'risk' => 'moderate',
                'removal_method' => 'manual_instructions',
                'status' => 'needs_user_action',
                'title' => "Public profile on {$source}",
                'verification_status' => 'verified',
                'evidence' => [
                    'rule' => 'e_string',
                    'http_status' => 200,
                    'checked_url' => "https://{$source}.test/{$identifier}",
                    'body_sha256' => str_repeat('d', 64),
                    'checked_at' => now()->toIso8601String(),
                ],
            ]],
            'sources' => [[
                'name' => $source,
                'category' => 'developer_profile',
                'outcome' => 'found',
                'http_status' => 200,
            ]],
        ];
    }

    private function fakeScanner(array $report): void
    {
        $mock = Mockery::mock(PythonScannerClient::class);
        $mock->shouldReceive('scanReport')->andReturn($report);
        $this->app->instance(PythonScannerClient::class, $mock);
    }

    public function test_a_subscription_without_a_next_run_is_dispatched(): void
    {
        Queue::fake();
        $profile = $this->profile();

        MonitoringSubscription::create([
            'identity_profile_id' => $profile->id,
            'is_active' => true,
            'cadence' => 'weekly',
            'next_scan_at' => null,
        ]);

        (new DispatchDueMonitoringScans)->handle(app(ScanOrchestrator::class));

        $this->assertSame(1, Scan::count());
    }

    public function test_a_subscription_that_is_not_due_is_skipped(): void
    {
        Queue::fake();
        $profile = $this->profile();

        MonitoringSubscription::create([
            'identity_profile_id' => $profile->id,
            'is_active' => true,
            'cadence' => 'weekly',
            'next_scan_at' => now()->addDays(3),
        ]);

        (new DispatchDueMonitoringScans)->handle(app(ScanOrchestrator::class));

        $this->assertSame(0, Scan::count());
    }

    public function test_monitoring_waits_when_the_owner_already_has_a_scan_running(): void
    {
        Queue::fake();
        $profile = $this->profile();

        $subscription = MonitoringSubscription::create([
            'identity_profile_id' => $profile->id,
            'is_active' => true,
            'cadence' => 'weekly',
            'next_scan_at' => now()->subDay(),
        ]);

        Scan::create([
            'identity_profile_id' => $profile->id,
            'type' => 'deep_scan',
            'status' => 'checking_sources',
            'progress_percentage' => 40,
            'current_stage' => 'Checking sources',
            'demo_mode' => false,
        ]);

        (new DispatchDueMonitoringScans)->handle(app(ScanOrchestrator::class));

        $this->assertSame(1, Scan::count());
        $this->assertTrue($subscription->fresh()->next_scan_at->isFuture());
    }

    public function test_missing_consent_advances_instead_of_retrying_every_hour(): void
    {
        Queue::fake();
        $profile = $this->profile(withConsent: false);

        $subscription = MonitoringSubscription::create([
            'identity_profile_id' => $profile->id,
            'is_active' => true,
            'cadence' => 'weekly',
            'next_scan_at' => now()->subDay(),
        ]);

        (new DispatchDueMonitoringScans)->handle(app(ScanOrchestrator::class));

        $this->assertSame(0, Scan::count());
        $this->assertTrue($subscription->fresh()->next_scan_at->isFuture());
    }

    public function test_the_plan_gate_blocks_users_without_a_plan_when_enabled(): void
    {
        Queue::fake();
        // Plan gates apply outside the free beta (v2.0 behaviour).
        config(['traceless.monitoring.enforce_plan' => true, 'traceless.beta' => false]);

        $profile = $this->profile();

        $subscription = MonitoringSubscription::create([
            'identity_profile_id' => $profile->id,
            'is_active' => true,
            'cadence' => 'weekly',
            'next_scan_at' => now()->subDay(),
        ]);

        (new DispatchDueMonitoringScans)->handle(app(ScanOrchestrator::class));

        $this->assertSame(0, Scan::count());
        $this->assertTrue($subscription->fresh()->next_scan_at->isFuture());
    }

    public function test_a_live_scan_raises_a_monitoring_alert(): void
    {
        Queue::fake();
        config(['traceless.demo_mode' => false]);

        $profile = $this->profile();

        MonitoringSubscription::create([
            'identity_profile_id' => $profile->id,
            'is_active' => true,
            'cadence' => 'weekly',
            'next_scan_at' => now(),
        ]);

        $this->fakeScanner($this->report());

        $orchestrator = app(ScanOrchestrator::class);
        $scan = $orchestrator->queueScan($profile, 'deep_scan');
        $orchestrator->executeScan($scan);

        $this->assertDatabaseHas('alerts', [
            'identity_profile_id' => $profile->id,
            'type' => 'new_exposure',
        ]);
    }

    public function test_reappearance_reopens_the_removal_task_and_says_so(): void
    {
        Queue::fake();
        config(['traceless.demo_mode' => false]);

        $profile = $this->profile();

        $finding = Finding::create([
            'identity_profile_id' => $profile->id,
            'source' => 'GitHub',
            'category' => 'developer_profile',
            'identifier_type' => 'username',
            'identifier_value' => 'janedoe',
            'confidence' => 0.9,
            'risk' => 'moderate',
            'removal_method' => 'manual_instructions',
            'status' => 'removed',
            'title' => 'Public profile on GitHub',
            'discovered_at' => now()->subMonth(),
            'last_checked_at' => now()->subMonth(),
        ]);

        $task = RemovalTask::create([
            'identity_profile_id' => $profile->id,
            'finding_id' => $finding->id,
            'source_name' => 'GitHub',
            'status' => 'removed',
            'workflow_type' => 'manual',
            'estimated_turnaround' => '1-7 days',
            'completed_at' => now()->subWeek(),
        ]);

        $this->fakeScanner($this->report());

        $orchestrator = app(ScanOrchestrator::class);
        $orchestrator->executeScan($orchestrator->queueScan($profile, 'snapshot'));

        $this->assertSame('reappeared', $finding->fresh()->status);
        $this->assertSame('ready_to_remove', $task->fresh()->status);
        $this->assertNull($task->fresh()->completed_at);
        $this->assertStringContainsString('Reappeared', (string) $task->fresh()->notes);

        $alert = Alert::where('identity_profile_id', $profile->id)->where('type', 'reappeared')->first();
        $this->assertNotNull($alert);
        $this->assertStringContainsString('reopened', $alert->message);
    }
}
