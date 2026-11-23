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
use Illuminate\Support\Facades\DB;
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

    private function seedIpCache(array $dataOverrides = []): void
    {
        config([
            'traceless.ip_intelligence.providers.ipapi_is.key' => 'test-ipapi',
            'traceless.ip_intelligence.providers.proxycheck.key' => 'test-proxycheck',
            'traceless.ip_intelligence.providers.abuseipdb.key' => 'test-abuseipdb',
            'traceless.ip_intelligence.providers.ip2location.key' => 'test-ip2location',
        ]);

        $data = array_merge([
            'ip' => '8.8.8.8',
            'ip_version' => 4,
            'country' => 'United States',
            'country_code' => 'US',
            'region' => 'California',
            'city' => 'Mountain View',
            'postal_code' => null,
            'latitude' => 37.4,
            'longitude' => -122.1,
            'timezone' => 'America/Los_Angeles',
            'isp' => 'Google LLC',
            'organisation' => 'Google LLC',
            'asn' => 'AS15169',
            'asn_organisation' => 'Google LLC',
            'network_range' => '8.8.8.0/24',
            'connection_type' => 'hosting',
            'classification' => 'hosting',
            'datacenter' => true,
            'is_vpn' => false,
            'is_proxy' => false,
            'is_tor' => false,
            'is_residential_proxy' => false,
            'is_open_proxy' => false,
            'is_hosting_proxy' => false,
            'anonymous' => false,
            'abuse_confidence' => 82,
            'abuse_reports' => 14,
            'abuse_last_reported' => '2026-11-01T10:00:00+00:00',
            'abuse_categories' => ['18'],
            'blacklist' => [],
            'confidence' => 100,
            'risk_score' => 34,
            'risk_grade' => 'C',
            'risk_classification' => 'moderate',
            'reasons' => ['Datacenter network'],
            'sources' => ['ipapi.is', 'AbuseIPDB'],
            'provider_status' => ['ipapi_is' => 'ok', 'proxycheck' => 'ok', 'abuseipdb' => 'ok', 'ip2location' => 'ok'],
            'analyzed_at' => now()->toIso8601String(),
            'expires_at' => now()->addHours(47)->toIso8601String(),
        ], $dataOverrides);

        DB::table('ip_intelligence_cache')->insert([
            'ip' => $data['ip'],
            'ip_version' => $data['ip_version'],
            'data' => json_encode($data),
            'provider_results' => json_encode([]),
            'provider_status' => json_encode($data['provider_status']),
            'risk_score' => $data['risk_score'],
            'risk_grade' => $data['risk_grade'],
            'risk_classification' => $data['risk_classification'],
            'confidence' => $data['confidence'],
            'analysis_ms' => 1200,
            'locked_until' => null,
            'lock_token' => null,
            'first_scanned_at' => now(),
            'last_scanned_at' => now(),
            'expires_at' => now()->addHours(47),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_the_ip_check_returns_cached_network_intelligence(): void
    {
        $user = User::factory()->create();
        $this->seedIpCache();

        Livewire::actingAs($user)
            ->test(IpCheck::class)
            ->set('ip', '8.8.8.8')
            ->call('check')
            ->assertSet('state', 'ready')
            ->assertSet('error', null)
            ->assertSee('Mountain View')
            ->assertSee('Google LLC')
            ->assertSee('AS15169')
            ->assertSee('Grade C')
            ->assertSee('Cached for 48h');
    }

    public function test_a_private_address_is_rejected_with_an_honest_message(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(IpCheck::class)
            ->set('ip', '127.0.0.1')
            ->call('check')
            ->assertSee('not a public IPv4 or IPv6 address')
            ->assertSet('state', 'idle');
    }

    public function test_the_ip_check_rejects_an_invalid_address(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(IpCheck::class)
            ->set('ip', 'not-an-ip')
            ->call('check')
            ->assertSee('not a public IPv4 or IPv6 address');
    }

    public function test_the_ip_check_reports_when_no_providers_are_configured(): void
    {
        config([
            'traceless.ip_intelligence.providers.ipapi_is.key' => '',
            'traceless.ip_intelligence.providers.proxycheck.key' => '',
            'traceless.ip_intelligence.providers.abuseipdb.key' => '',
            'traceless.ip_intelligence.providers.ip2location.key' => '',
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(IpCheck::class)
            ->set('ip', '8.8.8.8')
            ->call('check')
            ->assertSet('state', 'unavailable')
            ->assertSee('not configured');
    }

    public function test_the_ip_check_shows_abuse_reputation_from_the_cache(): void
    {
        $user = User::factory()->create();
        $this->seedIpCache();

        Livewire::actingAs($user)
            ->test(IpCheck::class)
            ->set('ip', '8.8.8.8')
            ->call('check')
            ->assertSee('82')
            ->assertSee('14')
            ->assertSee('Datacenter network');
    }

    public function test_the_ip_check_counts_down_to_the_next_fresh_scan(): void
    {
        $user = User::factory()->create();
        $this->seedIpCache();

        Livewire::actingAs($user)
            ->test(IpCheck::class)
            ->set('ip', '8.8.8.8')
            ->call('check')
            ->assertSee('Cached for 48h')
            ->assertSee('fresh scan in');
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
