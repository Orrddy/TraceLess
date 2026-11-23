<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeIpIntelligence;
use App\Services\IpIntelligence\IpIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class IpIntelligenceCacheRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private const IP = '203.0.113.10';

    // ---------------------------------------------------------------- helpers

    private function configureProviders(bool $configured = true): void
    {
        $key = $configured ? 'test-provider-key' : '';

        config([
            'traceless.ip_intelligence.providers.ipapi_is.key' => $key,
            'traceless.ip_intelligence.providers.proxycheck.key' => $key,
            'traceless.ip_intelligence.providers.abuseipdb.key' => $key,
            'traceless.ip_intelligence.providers.ip2location.key' => $key,
        ]);
    }

    private function visit(string $ip = self::IP): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->getJson('/api/ip-intelligence');
    }

    /**
     * @param  array<string, string>  $providerStatus
     */
    private function cacheRow(string $ip, array $providerStatus, string $ipVersion = '4'): void
    {
        DB::table('ip_intelligence_cache')->insert([
            'ip' => $ip,
            'ip_version' => (int) $ipVersion,
            'data' => json_encode([
                'ip' => $ip,
                'provider_status' => $providerStatus,
                'risk_score' => 40,
                'risk_grade' => 'C',
                'risk_classification' => 'moderate',
            ]),
            'provider_status' => json_encode($providerStatus),
            'risk_score' => 40,
            'risk_grade' => 'C',
            'risk_classification' => 'moderate',
            'expires_at' => now()->addHours(47),
            'first_scanned_at' => now()->subHour(),
            'last_scanned_at' => now()->subHour(),
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);
    }

    private function unavailableStatus(): array
    {
        return [
            'ipapi_is' => 'unavailable',
            'proxycheck' => 'unavailable',
            'abuseipdb' => 'unavailable',
            'ip2location' => 'unavailable',
        ];
    }

    private function okStatus(): array
    {
        return [
            'ipapi_is' => 'ok',
            'proxycheck' => 'ok',
            'abuseipdb' => 'ok',
            'ip2location' => 'ok',
        ];
    }

    private function fakeHealthyProviders(): void
    {
        Http::fake([
            'https://api.ipapi.is/*' => Http::response([
                'ip' => self::IP,
                'is_bogon' => false,
                'is_datacenter' => false,
                'is_tor' => false,
                'is_proxy' => false,
                'is_vpn' => false,
                'company' => ['name' => 'Example Telecom', 'type' => 'isp'],
                'asn' => ['asn' => 64500, 'org' => 'Example Telecom AS', 'route' => '203.0.113.0/24'],
                'location' => [
                    'country' => 'Australia',
                    'country_code' => 'AU',
                    'state' => 'Queensland',
                    'city' => 'Brisbane',
                    'zip' => '4000',
                    'latitude' => -27.4766,
                    'longitude' => 153.0166,
                    'timezone' => 'Australia/Brisbane',
                ],
            ]),
            'https://proxycheck.io/*' => Http::response([
                'status' => 'ok',
                self::IP => ['proxy' => 'no', 'type' => 'Business', 'risk' => 5],
            ]),
            'https://api.abuseipdb.com/*' => Http::response([
                'data' => ['abuseConfidenceScore' => 0, 'totalReports' => 0, 'countryCode' => 'AU'],
            ]),
            'https://api.ip2location.io/*' => Http::response([
                'ip' => self::IP,
                'country_code' => 'AU',
                'country_name' => 'Australia',
                'usage_type' => 'ISP',
            ]),
        ]);
    }

    private function runAnalysisJob(string $ip): void
    {
        $row = DB::table('ip_intelligence_cache')->where('ip', $ip)->firstOrFail();

        (new AnalyzeIpIntelligence($ip, (string) $row->lock_token))
            ->handle(app(IpIntelligenceService::class));
    }

    // ------------------------------------------------------- no provider keys

    public function test_with_no_provider_keys_the_endpoint_returns_503_without_caching_or_queueing(): void
    {
        Queue::fake();
        Http::fake();
        $this->configureProviders(false);

        $this->visit()
            ->assertStatus(503)
            ->assertJson(['status' => 'unavailable', 'data' => null]);

        Queue::assertNothingPushed();
        Http::assertNothingSent();
        $this->assertSame(0, DB::table('ip_intelligence_cache')->count());
        $this->assertSame(0, DB::table('ip_intelligence_metrics')->count());
    }

    public function test_a_private_or_invalid_ip_still_wins_over_the_no_key_response(): void
    {
        $this->configureProviders(false);

        $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->getJson('/api/ip-intelligence')
            ->assertStatus(422);
    }

    // ------------------------------------------------- zero-coverage repair

    public function test_a_zero_coverage_cache_row_is_reanalysed_when_keys_are_configured(): void
    {
        Queue::fake();
        $this->configureProviders(true);
        $this->cacheRow(self::IP, $this->unavailableStatus());

        $this->visit()->assertOk()->assertJsonPath('status', 'warming')->assertJsonPath('data', null);

        Queue::assertPushed(AnalyzeIpIntelligence::class, 1);

        // History is kept: same row, now locked for the replacement analysis.
        $row = DB::table('ip_intelligence_cache')->where('ip', self::IP)->firstOrFail();
        $this->assertNotNull($row->lock_token);
        $this->assertNotNull($row->locked_until);
        $this->assertSame(json_encode($this->unavailableStatus()), $row->provider_status);

        // A second visitor while the job is in flight still gets warming, with no
        // second job - the stale row is not served as ready.
        $this->visit()->assertOk()->assertJsonPath('status', 'warming');
        Queue::assertPushed(AnalyzeIpIntelligence::class, 1);

        $this->fakeHealthyProviders();
        $this->runAnalysisJob(self::IP);

        $response = $this->visit()->assertOk()->assertJsonPath('status', 'ready');
        $data = $response->json('data');

        $this->assertSame('ok', $data['provider_status']['ipapi_is']);
        $this->assertSame('ok', $data['provider_status']['ip2location']);
        Http::assertSentCount(4);
    }

    public function test_a_fully_ok_cache_row_is_still_served_without_provider_calls(): void
    {
        Queue::fake();
        Http::fake();
        $this->configureProviders(true);
        $this->cacheRow(self::IP, $this->okStatus());

        $response = $this->visit()->assertOk()->assertJsonPath('status', 'ready');

        $this->assertSame(self::IP, $response->json('data.ip'));
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_a_partially_ok_cache_row_is_still_served(): void
    {
        Queue::fake();
        Http::fake();
        $this->configureProviders(true);
        $this->cacheRow(self::IP, [
            'ipapi_is' => 'error',
            'proxycheck' => 'ok',
            'abuseipdb' => 'ok',
            'ip2location' => 'ok',
        ]);

        $this->visit()->assertOk()->assertJsonPath('status', 'ready');

        Queue::assertNothingPushed();
    }

    // ----------------------------------------------------------- ip:forget

    public function test_ip_forget_deletes_one_normalized_ip(): void
    {
        DB::table('ip_intelligence_cache')->insert([
            ['ip' => '203.0.113.10', 'ip_version' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['ip' => '203.0.113.11', 'ip_version' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->artisan('ip:forget', ['ip' => '203.0.113.10'])->assertExitCode(0);

        $this->assertDatabaseMissing('ip_intelligence_cache', ['ip' => '203.0.113.10']);
        $this->assertDatabaseHas('ip_intelligence_cache', ['ip' => '203.0.113.11']);
    }

    public function test_ip_forget_normalizes_ipv6_addresses(): void
    {
        DB::table('ip_intelligence_cache')->insert([
            ['ip' => '2001:4860:4860::8888', 'ip_version' => 6, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->artisan('ip:forget', ['ip' => '2001:4860:4860:0000:0000:0000:0000:8888'])->assertExitCode(0);

        $this->assertDatabaseMissing('ip_intelligence_cache', ['ip' => '2001:4860:4860::8888']);
    }

    public function test_ip_forget_all_clears_the_cache(): void
    {
        DB::table('ip_intelligence_cache')->insert([
            ['ip' => '203.0.113.10', 'ip_version' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['ip' => '203.0.113.11', 'ip_version' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->artisan('ip:forget', ['--all' => true])->assertExitCode(0);

        $this->assertSame(0, DB::table('ip_intelligence_cache')->count());
    }

    public function test_ip_forget_dry_run_deletes_nothing(): void
    {
        DB::table('ip_intelligence_cache')->insert([
            ['ip' => '203.0.113.10', 'ip_version' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->artisan('ip:forget', ['--all' => true, '--dry-run' => true])->assertExitCode(0);

        $this->assertDatabaseHas('ip_intelligence_cache', ['ip' => '203.0.113.10']);
    }

    public function test_ip_forget_requires_a_target_and_rejects_invalid_input(): void
    {
        $this->artisan('ip:forget')->assertExitCode(1);
        $this->artisan('ip:forget', ['ip' => '203.0.113.10', '--all' => true])->assertExitCode(1);
        $this->artisan('ip:forget', ['ip' => 'not-an-ip'])->assertExitCode(1);
        $this->artisan('ip:forget', ['ip' => '10.1.2.3'])->assertExitCode(1);
    }
}
