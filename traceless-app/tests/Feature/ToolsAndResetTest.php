<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\Dashboard;
use App\Livewire\Tools\IpCheck;
use App\Models\Alert;
use App\Models\ConsentRecord;
use App\Models\Finding;
use App\Models\IdentityProfile;
use App\Models\RemovalTask;
use App\Models\Report;
use App\Models\Scan;
use App\Models\User;
use App\Services\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ToolsAndResetTest extends TestCase
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

        return $profile;
    }

    public function test_the_tools_require_authentication(): void
    {
        $this->get('/tools/password-check')->assertRedirect('/login');
        $this->get('/tools/ip-check')->assertRedirect('/login');
    }

    public function test_the_password_check_page_renders_and_allows_the_breach_service(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/tools/password-check');

        $response->assertOk();
        $response->assertSee('Password check');
        $response->assertSee('passwordChecker', false);

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('https://api.pwnedpasswords.com', $csp);
    }

    public function test_the_ip_check_returns_network_details(): void
    {
        Http::fake([
            'ipinfo.io/*' => Http::response([
                'ip' => '8.8.8.8',
                'city' => 'Lagos',
                'region' => 'Lagos',
                'country' => 'NG',
                'org' => 'AS37027 Example Networks',
                'timezone' => 'Africa/Lagos',
            ], 200),
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(IpCheck::class)
            ->set('ip', '8.8.8.8')
            ->call('check')
            ->assertSee('Lagos')
            ->assertSee('AS37027 Example Networks')
            ->assertSet('error', null);
    }

    public function test_a_private_address_falls_back_to_the_public_one(): void
    {
        Http::fake([
            'ipinfo.io/json*' => Http::response([
                'ip' => '41.203.72.10',
                'city' => 'Lagos',
                'country' => 'NG',
                'org' => 'AS37027 Example Networks',
            ], 200),
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(IpCheck::class)
            ->set('ip', '127.0.0.1')
            ->call('check')
            ->assertSet('error', null)
            ->assertSee('41.203.72.10')
            ->assertSee('local or private');
    }

    public function test_the_ip_check_rejects_an_invalid_address(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(IpCheck::class)
            ->set('ip', 'not-an-ip')
            ->call('check')
            ->assertHasErrors('ip');
    }

    public function test_the_ip_check_reports_a_provider_failure(): void
    {
        Http::fake(['ipinfo.io/*' => Http::response([], 429)]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(IpCheck::class)
            ->set('ip', '8.8.8.8')
            ->call('check')
            ->assertSee('rate limiting');
    }

    public function test_resetting_clears_scan_data_but_keeps_the_identity_and_consent(): void
    {
        $profile = $this->profile();
        $user = $profile->user;

        $scan = Scan::create([
            'identity_profile_id' => $profile->id,
            'type' => 'snapshot',
            'status' => 'completed',
            'progress_percentage' => 100,
            'current_stage' => 'Completed',
            'demo_mode' => false,
        ]);

        $finding = Finding::create([
            'identity_profile_id' => $profile->id,
            'scan_id' => $scan->id,
            'source' => 'GitHub',
            'category' => 'developer_profile',
            'identifier_type' => 'username',
            'identifier_value' => 'janedoe',
            'confidence' => 0.9,
            'risk' => 'low',
            'removal_method' => 'manual_instructions',
            'status' => 'needs_user_action',
            'title' => 'Public profile on GitHub',
            'discovered_at' => now(),
            'last_checked_at' => now(),
        ]);

        RemovalTask::create([
            'identity_profile_id' => $profile->id,
            'finding_id' => $finding->id,
            'source_name' => 'GitHub',
            'status' => 'ready_to_remove',
            'workflow_type' => 'manual',
            'estimated_turnaround' => '1-7 days',
        ]);

        Report::create([
            'identity_profile_id' => $profile->id,
            'title' => 'Snapshot report',
            'report_type' => 'snapshot',
            'privacy_score' => 70,
            'total_findings' => 1,
            'summary' => '{}',
            'generated_at' => now(),
            'demo_mode' => false,
            'download_token' => str_repeat('e', 48),
        ]);

        Alert::create([
            'identity_profile_id' => $profile->id,
            'type' => 'new_exposure',
            'severity' => 'moderate',
            'title' => 'New record',
            'message' => 'A record appeared.',
            'is_read' => false,
        ]);

        Livewire::actingAs($user)->test(Dashboard::class)->call('resetForNewScan');

        $this->assertSame(0, Scan::count());
        $this->assertSame(0, Finding::count());
        $this->assertSame(0, RemovalTask::count());
        $this->assertSame(0, Report::count());
        $this->assertSame(0, Alert::count());

        // The identity and the consent that authorises the next scan remain.
        $this->assertDatabaseHas('identity_profiles', ['id' => $profile->id]);
        $this->assertSame(1, ConsentRecord::where('identity_profile_id', $profile->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'profile_reset']);
    }
}
