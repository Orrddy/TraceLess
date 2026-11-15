<?php

namespace Tests\Feature;

use App\Models\IdentityProfile;
use App\Models\Scan;
use App\Models\ScanSource;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\PythonScannerClient;
use App\Services\ScanOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class MultiUsernameScanTest extends TestCase
{
    use RefreshDatabase;

    private function profileWithUsernames(array $usernames): IdentityProfile
    {
        $user = User::factory()->create();

        $profile = IdentityProfile::create([
            'user_id' => $user->id,
            'full_name' => 'Jane Doe',
            'primary_email' => 'jane@example.com',
            'consent_acknowledged' => true,
            'consent_given_at' => now(),
        ]);

        foreach ($usernames as $username) {
            $profile->publicIdentifiers()->create([
                'type' => 'username',
                'value' => $username,
                'is_monitored' => true,
            ]);
        }

        app(ConsentService::class)->recordConsent($user, $profile, '127.0.0.1');

        return $profile;
    }

    private function reportFor(string $username, string $source, array $sources): array
    {
        return [
            'findings' => [[
                'source' => $source,
                'category' => 'social_profile',
                'identifier_type' => 'username',
                'identifier_value' => $username,
                'url' => "https://{$source}.test/{$username}",
                'confidence' => 0.9,
                'risk' => 'moderate',
                'removal_method' => 'manual_instructions',
                'status' => 'needs_user_action',
                'title' => "Public profile on {$source}",
                'verification_status' => 'verified',
                'evidence' => [
                    'rule' => 'e_string',
                    'http_status' => 200,
                    'checked_url' => "https://{$source}.test/{$username}",
                    'body_sha256' => str_repeat('c', 64),
                    'checked_at' => now()->toIso8601String(),
                ],
            ]],
            'sources' => $sources,
        ];
    }

    public function test_every_monitored_username_is_scanned(): void
    {
        config(['traceless.demo_mode' => false]);
        Queue::fake();

        $profile = $this->profileWithUsernames(['alpha', 'beta']);

        $scanner = Mockery::mock(PythonScannerClient::class);
        $scanner->shouldReceive('scanReport')
            ->with('username', 'alpha', true)
            ->once()
            ->andReturn($this->reportFor('alpha', 'GitHub', [
                ['name' => 'GitHub', 'category' => 'developer_profile', 'outcome' => 'found', 'http_status' => 200],
                ['name' => 'Reddit', 'category' => 'social_profile', 'outcome' => 'not_found'],
            ]));
        $scanner->shouldReceive('scanReport')
            ->with('username', 'beta', true)
            ->once()
            ->andReturn($this->reportFor('beta', 'Keybase', [
                ['name' => 'GitHub', 'category' => 'developer_profile', 'outcome' => 'not_found'],
                ['name' => 'Reddit', 'category' => 'social_profile', 'outcome' => 'found', 'http_status' => 200],
                ['name' => 'Keybase', 'category' => 'social_profile', 'outcome' => 'found', 'http_status' => 200],
            ]));
        $this->app->instance(PythonScannerClient::class, $scanner);

        $orchestrator = app(ScanOrchestrator::class);
        $scan = $orchestrator->queueScan($profile, 'snapshot');
        $orchestrator->executeScan($scan);

        $scan->refresh();

        $this->assertSame('completed', $scan->status);
        $this->assertSame(2, $scan->findings_count);

        $this->assertDatabaseHas('findings', ['identifier_value' => 'alpha', 'source' => 'GitHub']);
        $this->assertDatabaseHas('findings', ['identifier_value' => 'beta', 'source' => 'Keybase']);

        // One row per source, not one row per username (the synthetic breach
        // provider row is added separately and carries the credentials category).
        $this->assertSame(3, ScanSource::where('scan_id', $scan->id)->where('category', '!=', 'credentials')->count());
        $this->assertDatabaseHas('scan_sources', [
            'scan_id' => $scan->id,
            'name' => 'Breach metadata provider',
            'outcome' => 'skipped',
        ]);

        // "found" wins over "not_found" during the merge.
        $this->assertSame('found', ScanSource::where('scan_id', $scan->id)->where('name', 'GitHub')->value('outcome'));
        $this->assertSame('found', ScanSource::where('scan_id', $scan->id)->where('name', 'Reddit')->value('outcome'));
    }

    public function test_the_username_count_is_capped(): void
    {
        config(['traceless.demo_mode' => false, 'traceless.scan.max_usernames' => 2]);
        Queue::fake();

        $profile = $this->profileWithUsernames(['one', 'two', 'three']);

        $scanner = Mockery::mock(PythonScannerClient::class);
        $scanner->shouldReceive('scanReport')->twice()->andReturn($this->reportFor('x', 'GitHub', []));
        $this->app->instance(PythonScannerClient::class, $scanner);

        $orchestrator = app(ScanOrchestrator::class);
        $orchestrator->executeScan($orchestrator->queueScan($profile, 'snapshot'));

        $this->assertSame(1, Scan::count());
    }

    public function test_a_live_scan_without_usernames_reports_no_sources(): void
    {
        config(['traceless.demo_mode' => false]);
        Queue::fake();

        $profile = $this->profileWithUsernames([]);

        $scanner = Mockery::mock(PythonScannerClient::class);
        $scanner->shouldNotReceive('scanReport');
        $this->app->instance(PythonScannerClient::class, $scanner);

        $orchestrator = app(ScanOrchestrator::class);
        $scan = $orchestrator->queueScan($profile, 'snapshot');
        $orchestrator->executeScan($scan);

        $scan->refresh();

        $this->assertSame('completed', $scan->status);
        $this->assertSame(0, $scan->findings_count);
    }
}
