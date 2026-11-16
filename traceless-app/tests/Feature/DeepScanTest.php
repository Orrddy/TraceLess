<?php

namespace Tests\Feature;

use App\Models\Finding;
use App\Models\IdentityProfile;
use App\Models\Scan;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\PythonScannerClient;
use App\Services\ScanOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class DeepScanTest extends TestCase
{
    use RefreshDatabase;

    private function profile(): IdentityProfile
    {
        $user = User::factory()->create();

        $profile = IdentityProfile::create([
            'user_id' => $user->id,
            'full_name' => 'Jane Doe',
            'primary_email' => 'jane@example.com',
            'consent_acknowledged' => true,
            'consent_given_at' => now(),
        ]);

        app(ConsentService::class)->recordConsent($user, $profile, '127.0.0.1');

        $profile->publicIdentifiers()->create(['type' => 'username', 'value' => 'janedoe', 'is_monitored' => true]);
        $profile->publicIdentifiers()->create(['type' => 'email', 'value' => 'jane@example.com', 'is_monitored' => true]);
        $profile->publicIdentifiers()->create(['type' => 'phone', 'value' => '+12025550143', 'is_monitored' => true]);

        return $profile;
    }

    private function report(string $source, string $type, bool $verified = false): array
    {
        return [
            'findings' => [[
                'source' => $source,
                'category' => 'public_directory',
                'identifier_type' => $type,
                'identifier_value' => 'x',
                'url' => "https://{$source}.test/x",
                'confidence' => 0.7,
                'risk' => 'moderate',
                'removal_method' => 'manual_instructions',
                'status' => 'needs_user_action',
                'title' => "Account on {$source}",
                'verification_status' => $verified ? 'verified' : 'unverified',
                'evidence' => ['rule' => 'account_probe', 'checked_url' => "https://{$source}.test/x"],
            ]],
            'sources' => [[
                'name' => $source,
                'category' => 'public_directory',
                'outcome' => 'found',
            ]],
        ];
    }

    public function test_a_deep_scan_checks_the_email_as_well_as_the_usernames(): void
    {
        Queue::fake();
        config(['traceless.demo_mode' => false]);

        $profile = $this->profile();

        $mock = Mockery::mock(PythonScannerClient::class);
        $mock->shouldReceive('scanReport')
            ->once()
            ->with('username', 'janedoe', true, 'deep')
            ->andReturn($this->report('GitHub', 'username'));
        $mock->shouldReceive('scanReport')
            ->once()
            ->with('email', 'jane@example.com', true, 'deep')
            ->andReturn($this->report('Letterboxd', 'email'));
        $mock->shouldReceive('scanReport')
            ->once()
            ->with('phone', '+12025550143', true, 'deep')
            ->andReturn($this->report('Zoho', 'phone'));
        $this->app->instance(PythonScannerClient::class, $mock);

        $orchestrator = app(ScanOrchestrator::class);
        $orchestrator->executeScan($orchestrator->queueScan($profile, 'deep_scan'));

        $this->assertSame(3, Finding::count());
        $this->assertDatabaseHas('findings', ['source' => 'Letterboxd', 'identifier_type' => 'email', 'verification_status' => 'unverified']);
        $this->assertDatabaseHas('findings', ['source' => 'Zoho', 'identifier_type' => 'phone', 'verification_status' => 'unverified']);
        $this->assertDatabaseHas('scan_sources', ['name' => 'GitHub']);
        $this->assertDatabaseHas('scan_sources', ['name' => 'Letterboxd']);
    }

    public function test_a_snapshot_scan_stays_on_usernames_only(): void
    {
        Queue::fake();
        config(['traceless.demo_mode' => false]);

        $profile = $this->profile();

        $mock = Mockery::mock(PythonScannerClient::class);
        $mock->shouldReceive('scanReport')
            ->once()
            ->with('username', 'janedoe', true, 'curated')
            ->andReturn($this->report('GitHub', 'username'));
        $mock->shouldNotReceive('scanReport')->withArgs(fn ($type) => $type === 'email');
        $this->app->instance(PythonScannerClient::class, $mock);

        $orchestrator = app(ScanOrchestrator::class);
        $orchestrator->executeScan($orchestrator->queueScan($profile, 'snapshot'));

        $this->assertSame(1, Finding::count());
    }

    public function test_one_failing_identifier_does_not_discard_the_others(): void
    {
        Queue::fake();
        config(['traceless.demo_mode' => false]);

        $profile = $this->profile();

        $mock = Mockery::mock(PythonScannerClient::class);
        $mock->shouldReceive('scanReport')
            ->with('username', 'janedoe', true, 'deep')
            ->andReturn($this->report('GitHub', 'username'));
        $mock->shouldReceive('scanReport')
            ->with('email', 'jane@example.com', true, 'deep')
            ->andThrow(new \RuntimeException('Email probe failed.'));
        $mock->shouldReceive('scanReport')
            ->with('phone', '+12025550143', true, 'deep')
            ->andReturn(['findings' => [], 'sources' => []]);
        $this->app->instance(PythonScannerClient::class, $mock);

        $orchestrator = app(ScanOrchestrator::class);
        $orchestrator->executeScan($orchestrator->queueScan($profile, 'deep_scan'));

        $this->assertSame(1, Finding::count());
        $this->assertDatabaseHas('findings', ['source' => 'GitHub']);
    }
}
