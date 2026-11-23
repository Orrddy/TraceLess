<?php

namespace Tests\Feature;

use App\Models\Finding;
use App\Models\IdentityProfile;
use App\Models\ScanSource;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\PythonScannerClient;
use App\Services\ScanOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class LiveScanModeTest extends TestCase
{
    use RefreshDatabase;

    protected function makeProfile(User $user): IdentityProfile
    {
        $profile = IdentityProfile::create([
            'user_id' => $user->id,
            'full_name' => 'Jane Doe',
            'primary_email' => 'jane@example.com',
            'privacy_score' => 100,
        ]);

        $profile->publicIdentifiers()->create([
            'type' => 'username',
            'value' => 'janedoe',
            'is_monitored' => true,
        ]);

        app(ConsentService::class)->recordConsent($user, $profile, '127.0.0.1');

        return $profile;
    }

    protected function fakeScanner(array $report): void
    {
        $mock = Mockery::mock(PythonScannerClient::class);
        $mock->shouldReceive('scanReport')
            ->once()
            ->andReturn($report);
        $mock->shouldNotReceive('scan');

        $this->app->instance(PythonScannerClient::class, $mock);
    }

    protected function verifiedReport(): array
    {
        return [
            'findings' => [
                [
                    'source' => 'GitHub',
                    'category' => 'developer_profile',
                    'identifier_type' => 'username',
                    'identifier_value' => 'janedoe',
                    'url' => 'https://github.com/janedoe',
                    'confidence' => 0.95,
                    'risk' => 'low',
                    'removal_method' => 'manual_instructions',
                    'status' => 'needs_user_action',
                    'title' => 'Public profile on GitHub',
                    'description' => 'Matched the source existence rule.',
                    'action_guidance' => 'Review the profile.',
                    'verification_status' => 'verified',
                    'evidence' => [
                        'rule' => 'e_string',
                        'http_status' => 200,
                        'checked_url' => 'https://github.com/janedoe',
                        'body_sha256' => str_repeat('b', 64),
                        'checked_at' => now()->toIso8601String(),
                    ],
                ],
            ],
            'sources' => [
                [
                    'name' => 'GitHub',
                    'category' => 'developer_profile',
                    'outcome' => 'found',
                    'http_status' => 200,
                    'checked_url' => 'https://github.com/janedoe',
                ],
                [
                    'name' => 'Reddit',
                    'category' => 'social_profile',
                    'outcome' => 'unknown',
                    'reason' => 'source_blocked_request',
                ],
            ],
        ];
    }

    public function test_live_scan_persists_verified_findings_and_source_telemetry(): void
    {
        Queue::fake();
        config(['traceless.demo_mode' => false, 'traceless.scan.stage_delay_ms' => 0]);

        $user = User::factory()->create();
        $profile = $this->makeProfile($user);
        $this->fakeScanner($this->verifiedReport());

        $orchestrator = app(ScanOrchestrator::class);
        $scan = $orchestrator->queueScan($profile, 'snapshot');
        $this->assertFalse($scan->demo_mode);

        $orchestrator->executeScan($scan);
        $scan->refresh();

        $this->assertSame('completed', $scan->status);

        $finding = Finding::where('scan_id', $scan->id)->firstOrFail();
        $this->assertSame('verified', $finding->verification_status);
        $this->assertSame(200, $finding->http_status);
        $this->assertNotNull($finding->verified_at);
        $this->assertSame('e_string', $finding->evidence['rule']);

        // Two contacted sources plus the skipped breach provider.
        $this->assertSame(3, $scan->scanSources()->count());
        $this->assertSame(3, $scan->sources_total);

        $this->assertDatabaseHas('scan_sources', [
            'scan_id' => $scan->id,
            'name' => 'GitHub',
            'outcome' => 'found',
            'http_status' => 200,
        ]);

        $this->assertDatabaseHas('scan_sources', [
            'scan_id' => $scan->id,
            'name' => 'Reddit',
            'outcome' => 'unknown',
            'outcome_reason' => 'source_blocked_request',
        ]);

        $this->assertDatabaseHas('scan_sources', [
            'scan_id' => $scan->id,
            'category' => 'credentials',
            'outcome' => 'skipped',
            'outcome_reason' => 'provider_not_configured',
        ]);

        $this->assertDatabaseCount('credential_exposures', 0);
    }

    public function test_live_scan_marks_absent_source_findings_as_unverified(): void
    {
        Queue::fake();
        config(['traceless.demo_mode' => false, 'traceless.scan.stage_delay_ms' => 0]);

        $user = User::factory()->create();
        $profile = $this->makeProfile($user);

        $stale = Finding::create([
            'identity_profile_id' => $profile->id,
            'source' => 'Reddit',
            'category' => 'social_profile',
            'identifier_type' => 'username',
            'identifier_value' => 'janedoe',
            'confidence' => 0.8,
            'risk' => 'moderate',
            'removal_method' => 'manual_instructions',
            'status' => 'needs_user_action',
            'verification_status' => 'verified',
            'title' => 'Old Reddit profile',
        ]);

        $this->fakeScanner([
            'findings' => [],
            'sources' => [
                [
                    'name' => 'Reddit',
                    'category' => 'social_profile',
                    'outcome' => 'not_found',
                    'http_status' => 404,
                ],
            ],
        ]);

        $orchestrator = app(ScanOrchestrator::class);
        $orchestrator->executeScan($orchestrator->queueScan($profile, 'snapshot'));

        $stale->refresh();
        $this->assertSame('could_not_verify', $stale->status);
        $this->assertNotNull($stale->verified_at);
    }

    public function test_live_scan_without_a_monitored_username_does_not_call_the_worker(): void
    {
        Queue::fake();
        config(['traceless.demo_mode' => false, 'traceless.scan.stage_delay_ms' => 0]);

        $user = User::factory()->create();
        $profile = IdentityProfile::create([
            'user_id' => $user->id,
            'full_name' => 'Jane Doe',
            'primary_email' => 'jane@example.com',
            'privacy_score' => 100,
        ]);
        app(ConsentService::class)->recordConsent($user, $profile, '127.0.0.1');

        $mock = Mockery::mock(PythonScannerClient::class);
        $mock->shouldNotReceive('scanReport');
        $this->app->instance(PythonScannerClient::class, $mock);

        $orchestrator = app(ScanOrchestrator::class);
        $scan = $orchestrator->queueScan($profile, 'snapshot');
        $orchestrator->executeScan($scan);

        $this->assertSame('completed', $scan->fresh()->status);
        $this->assertSame(0, Finding::count());
        $this->assertSame(1, ScanSource::where('scan_id', $scan->id)->count());
    }

    public function test_a_live_scan_fails_instead_of_claiming_an_all_clear_when_the_scanner_is_down(): void
    {
        Queue::fake();
        config(['traceless.demo_mode' => false, 'traceless.scan.stage_delay_ms' => 0]);

        $user = User::factory()->create();
        $profile = $this->makeProfile($user);

        $mock = Mockery::mock(PythonScannerClient::class);
        $mock->shouldReceive('scanReport')->andThrow(new \RuntimeException('Scanner worker is not installed.'));
        $this->app->instance(PythonScannerClient::class, $mock);

        $orchestrator = app(ScanOrchestrator::class);
        $scan = $orchestrator->queueScan($profile, 'snapshot');
        $orchestrator->executeScan($scan);

        $scan->refresh();

        $this->assertSame('failed', $scan->fresh()->status);
        $this->assertStringContainsString('retry', strtolower((string) $scan->error_message));
        $this->assertDatabaseCount('reports', 0);
        $this->assertSame(0, Finding::count());
    }
}
