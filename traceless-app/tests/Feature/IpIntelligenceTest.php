<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeIpIntelligence;
use App\Services\IpIntelligence\IpIntelligenceMetrics;
use App\Services\IpIntelligence\IpIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class IpIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    private const IP = '203.0.113.10';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->configureProviders();
        config(['traceless.ip_intelligence.retry_backoff_ms' => 0]);
    }

    // ---------------------------------------------------------------- helpers

    private function configureProviders(): void
    {
        config([
            'traceless.ip_intelligence.providers.ipapi_is.key' => 'test-ipapi-key',
            'traceless.ip_intelligence.providers.proxycheck.key' => 'test-proxycheck-key',
            'traceless.ip_intelligence.providers.abuseipdb.key' => 'test-abuseipdb-key',
            'traceless.ip_intelligence.providers.ip2location.key' => 'test-ip2location-key',
        ]);
    }

    private function ipapiPayload(string $ip = self::IP): array
    {
        return [
            'ip' => $ip,
            'is_bogon' => false,
            'is_mobile' => false,
            'is_datacenter' => false,
            'is_tor' => false,
            'is_proxy' => false,
            'is_vpn' => false,
            'is_abuser' => false,
            'company' => [
                'name' => 'Example Telecom',
                'type' => 'isp',
                'abuser_score' => '0.0100 (Very Low)',
            ],
            'asn' => [
                'asn' => 64500,
                'org' => 'Example Telecom AS',
                'route' => '203.0.113.0/24',
                'type' => 'isp',
                'domain' => 'example.test',
            ],
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
            'elapsed_ms' => 12.4,
        ];
    }

    private function proxycheckPayload(string $ip = self::IP): array
    {
        return [
            'status' => 'ok',
            $ip => [
                'asn' => 'AS64500',
                'provider' => 'Example Telecom',
                'organisation' => 'Example Telecom Pty',
                'range' => '203.0.113.0/24',
                'country' => 'Australia',
                'isocode' => 'AU',
                'region' => 'Queensland',
                'city' => 'Brisbane',
                'proxy' => 'no',
                'type' => 'Business',
                'risk' => 12,
            ],
        ];
    }

    private function abusePayload(): array
    {
        return [
            'data' => [
                'ipAddress' => self::IP,
                'isPublic' => true,
                'ipVersion' => 4,
                'isWhitelisted' => false,
                'abuseConfidenceScore' => 0,
                'countryCode' => 'AU',
                'usageType' => 'Fixed Line ISP',
                'isp' => 'Example Telecom',
                'domain' => 'example.test',
                'hostnames' => [],
                'totalReports' => 0,
                'numDistinctUsers' => 0,
                'lastReportedAt' => null,
                'reports' => [],
            ],
        ];
    }

    private function ip2locationPayload(): array
    {
        return [
            'ip' => self::IP,
            'country_code' => 'AU',
            'country_name' => 'Australia',
            'region_name' => 'Queensland',
            'city_name' => 'Brisbane',
            'latitude' => -27.4766,
            'longitude' => 153.0166,
            'zip_code' => '4000',
            'time_zone' => 'Australia/Brisbane',
            'asn' => '64500',
            'as_name' => 'EXAMPLE-AS',
            'isp' => 'Example Telecom',
            'domain' => 'example.test',
            'usage_type' => 'ISP',
            'is_proxy' => false,
            'is_vpn' => false,
            'is_tor' => false,
            'is_datacenter' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fakeProviders(array $overrides = []): void
    {
        Http::fake(array_merge([
            'https://api.ipapi.is/*' => Http::response($this->ipapiPayload()),
            'https://proxycheck.io/*' => Http::response($this->proxycheckPayload()),
            'https://api.abuseipdb.com/*' => Http::response($this->abusePayload()),
            'https://api.ip2location.io/*' => Http::response($this->ip2locationPayload()),
        ], $overrides));
    }

    private function visit(string $ip, array $headers = []): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeaders($headers)
            ->getJson('/api/ip-intelligence');
    }

    private function runAnalysisJob(string $ip): void
    {
        $row = DB::table('ip_intelligence_cache')->where('ip', $ip)->firstOrFail();

        (new AnalyzeIpIntelligence($ip, (string) $row->lock_token))
            ->handle(app(IpIntelligenceService::class));
    }

    /**
     * @return array<string, mixed>
     */
    private function storedData(string $ip): array
    {
        $row = DB::table('ip_intelligence_cache')->where('ip', $ip)->firstOrFail();
        $decoded = json_decode((string) $row->data, true);

        $this->assertIsArray($decoded);

        return $decoded;
    }

    // -------------------------------------------------------------- 1. warming

    public function test_an_unseen_ip_warms_and_then_becomes_ready_after_the_job(): void
    {
        $this->visit(self::IP)
            ->assertOk()
            ->assertJson(['status' => 'warming', 'data' => null]);

        Queue::assertPushed(AnalyzeIpIntelligence::class, 1);

        $row = DB::table('ip_intelligence_cache')->where('ip', self::IP)->firstOrFail();
        $this->assertNotNull($row->locked_until);
        $this->assertNull($row->data);

        $this->fakeProviders();
        $this->runAnalysisJob(self::IP);

        $response = $this->visit(self::IP)->assertOk()->assertJsonPath('status', 'ready');
        $data = $response->json('data');

        $this->assertSame(self::IP, $data['ip']);
        $this->assertSame(4, $data['ip_version']);
        $this->assertSame('Australia', $data['country']);
        $this->assertSame('AU', $data['country_code']);
        $this->assertSame('Brisbane', $data['city']);
        $this->assertSame('Example Telecom', $data['isp']);
        $this->assertSame(64500, $data['asn']);
        $this->assertSame('residential', $data['classification']);
        $this->assertSame(['ipapi_is', 'proxycheck', 'abuseipdb', 'ip2location'], array_keys($data['provider_status']));
        $this->assertSame('ok', $data['provider_status']['ipapi_is']);
        $this->assertContains('ipapi.is', $data['sources']);
        $this->assertSame(100, $data['confidence']);
        $this->assertGreaterThanOrEqual(0, $data['risk_score']);
        $this->assertLessThanOrEqual(100, $data['risk_score']);
        $this->assertContains($data['risk_grade'], ['A', 'B', 'C', 'D', 'E', 'F']);
        $this->assertContains($data['risk_classification'], ['low', 'moderate', 'high', 'critical']);
        $this->assertIsArray($data['reasons']);
        $this->assertNotEmpty($data['analyzed_at']);
        $this->assertNotEmpty($data['expires_at']);
        $this->assertSame([], $data['blacklist']);
        $this->assertIsArray($data['abuse_categories']);

        $expires = Carbon::parse($data['expires_at']);
        $this->assertTrue(
            $expires->between(now()->addHours(47), now()->addHours(49)),
            'The cache window should be 48 hours.'
        );
    }

    // ------------------------------------------------------------ 2. cache hit

    public function test_a_cached_ip_is_reused_without_calling_providers_again(): void
    {
        $this->visit(self::IP)->assertJsonPath('status', 'warming');
        $this->fakeProviders();
        $this->runAnalysisJob(self::IP);
        $this->visit(self::IP)->assertJsonPath('status', 'ready');

        Http::assertSentCount(4);

        $this->visit(self::IP)->assertJsonPath('status', 'ready');
        Http::assertSentCount(4);
        Queue::assertPushed(AnalyzeIpIntelligence::class, 1);
    }

    // ------------------------------------------- 3. concurrent request dedupe

    public function test_concurrent_requests_for_one_ip_dispatch_a_single_job(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->visit(self::IP)->assertOk()->assertJsonPath('status', 'warming');
        }

        Queue::assertPushed(AnalyzeIpIntelligence::class, 1);
        $this->assertSame(1, DB::table('ip_intelligence_cache')->count());
    }

    // --------------------------------------------------------- 4. cache expiry

    public function test_an_expired_cache_entry_triggers_a_fresh_analysis(): void
    {
        $this->visit(self::IP)->assertJsonPath('status', 'warming');
        $this->fakeProviders();
        $this->runAnalysisJob(self::IP);
        $this->visit(self::IP)->assertJsonPath('status', 'ready');

        DB::table('ip_intelligence_cache')->where('ip', self::IP)->update([
            'expires_at' => now()->subMinute(),
        ]);

        $this->visit(self::IP)->assertOk()->assertJsonPath('status', 'warming');
        Queue::assertPushed(AnalyzeIpIntelligence::class, 2);
    }

    // ----------------------------------------------------- 5. provider timeout

    public function test_a_provider_timeout_is_reported_and_other_providers_still_merge(): void
    {
        $this->visit(self::IP)->assertJsonPath('status', 'warming');

        $this->fakeProviders([
            'https://api.ipapi.is/*' => function (): never {
                throw new ConnectionException('cURL error 28: Operation timed out after 3001 milliseconds');
            },
        ]);

        $this->runAnalysisJob(self::IP);

        $data = $this->storedData(self::IP);

        $this->assertSame('timeout', $data['provider_status']['ipapi_is']);
        $this->assertSame('ok', $data['provider_status']['ip2location']);
        $this->assertSame('Australia', $data['country']);
        $this->assertSame('Brisbane', $data['city']);
        $this->assertLessThan(100, $data['confidence']);
        $this->assertStringContainsString('limited provider coverage', implode(' ', array_map('strtolower', $data['reasons'])));

        $this->assertDatabaseHas('ip_intelligence_metrics', ['metric' => 'provider.ipapi_is.timeout', 'value' => 1]);
        $this->assertDatabaseHas('ip_intelligence_metrics', ['metric' => 'provider.ipapi_is.call', 'value' => 2]);
    }

    // -------------------------------------------------------- 6. rate limiting

    public function test_a_429_is_handled_without_retrying_the_provider(): void
    {
        $this->visit(self::IP)->assertJsonPath('status', 'warming');

        $this->fakeProviders([
            'https://proxycheck.io/*' => Http::response(['status' => 'error', 'message' => 'rate limit'], 429),
        ]);

        $this->runAnalysisJob(self::IP);

        $data = $this->storedData(self::IP);

        $this->assertSame('error', $data['provider_status']['proxycheck']);
        $this->assertSame('ok', $data['provider_status']['ipapi_is']);

        $proxycheckRequests = Http::recorded(
            fn ($request) => str_contains($request->url(), 'proxycheck.io')
        );

        $this->assertCount(1, $proxycheckRequests, 'A 429 must not be retried.');
        $this->assertDatabaseHas('ip_intelligence_metrics', ['metric' => 'provider.proxycheck.rate_limited', 'value' => 1]);
    }

    // ------------------------------------------------------ 7. partial failure

    public function test_a_partial_provider_failure_merges_only_available_data(): void
    {
        $this->visit(self::IP)->assertJsonPath('status', 'warming');

        $this->fakeProviders([
            'https://api.abuseipdb.com/*' => Http::sequence()->pushStatus(500)->pushStatus(500),
        ]);

        $this->runAnalysisJob(self::IP);

        $data = $this->storedData(self::IP);

        $this->assertSame('error', $data['provider_status']['abuseipdb']);
        $this->assertNull($data['abuse_confidence']);
        $this->assertNull($data['abuse_reports']);
        $this->assertSame('Australia', $data['country']);
        $this->assertSame(80, $data['confidence']);
        $this->assertDatabaseHas('ip_intelligence_metrics', ['metric' => 'provider.abuseipdb.error', 'value' => 1]);

        $expires = Carbon::parse($data['expires_at']);
        $this->assertTrue(
            $expires->between(now()->addHours(5), now()->addHours(7)),
            'A three-provider analysis should use the degraded cache window.'
        );
    }

    // --------------------------------------------------------- 8. full outage

    public function test_a_full_provider_outage_returns_an_honest_unknown_record(): void
    {
        $this->visit(self::IP)->assertJsonPath('status', 'warming');

        $this->fakeProviders([
            'https://api.ipapi.is/*' => function (): never {
                throw new ConnectionException('cURL error 28: Operation timed out after 3001 milliseconds');
            },
            'https://proxycheck.io/*' => function (): never {
                throw new ConnectionException('cURL error 7: Failed to connect');
            },
            'https://api.abuseipdb.com/*' => Http::sequence()->pushStatus(500)->pushStatus(500),
            'https://api.ip2location.io/*' => Http::sequence()->pushStatus(503)->pushStatus(503),
        ]);

        $this->runAnalysisJob(self::IP);

        $data = $this->storedData(self::IP);

        $this->assertNull($data['country']);
        $this->assertNull($data['country_code']);
        $this->assertNull($data['isp']);
        $this->assertNull($data['is_vpn']);
        $this->assertSame('unknown', $data['classification']);
        $this->assertSame('timeout', $data['provider_status']['ipapi_is']);
        $this->assertSame('error', $data['provider_status']['proxycheck']);
        $this->assertSame([], $data['sources']);
        $this->assertSame(0, $data['confidence']);
        $this->assertGreaterThanOrEqual(40, $data['risk_score']);
        $this->assertNotSame('A', $data['risk_grade']);
        $this->assertStringContainsString(
            'limited provider coverage',
            implode(' ', array_map('strtolower', $data['reasons']))
        );

        $expires = Carbon::parse($data['expires_at']);
        $this->assertTrue(
            $expires->between(now()->addMinutes(9), now()->addMinutes(11)),
            'A zero-provider analysis should expire quickly instead of freezing for 48 hours.'
        );
    }

    // --------------------------------------------------- 9. worker concurrency

    public function test_a_duplicate_job_with_a_stale_lock_token_does_not_rerun_the_analysis(): void
    {
        $this->visit(self::IP)->assertJsonPath('status', 'warming');
        $row = DB::table('ip_intelligence_cache')->where('ip', self::IP)->firstOrFail();
        $token = (string) $row->lock_token;

        $this->fakeProviders();
        (new AnalyzeIpIntelligence(self::IP, $token))->handle(app(IpIntelligenceService::class));

        Http::assertSentCount(4);

        (new AnalyzeIpIntelligence(self::IP, $token))->handle(app(IpIntelligenceService::class));

        Http::assertSentCount(4);
        $this->assertDatabaseHas('ip_intelligence_metrics', ['metric' => 'analysis.completed', 'value' => 1]);
    }

    // ------------------------------------------ 10. invalid and spoofed IPs

    public function test_an_invalid_visitor_ip_is_rejected_without_queueing_work(): void
    {
        $this->visit('not-an-ip')->assertStatus(422)->assertJson(['status' => 'error', 'data' => null]);

        Queue::assertNothingPushed();
        Http::assertNothingSent();
        $this->assertSame(0, DB::table('ip_intelligence_cache')->count());
    }

    public function test_a_private_visitor_ip_is_rejected(): void
    {
        $this->visit('10.1.2.3')->assertStatus(422);

        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_forwarded_for_headers_are_ignored_when_no_proxy_is_trusted(): void
    {
        $this->visit('203.0.113.99', ['X-Forwarded-For' => '8.8.8.8', 'X-Real-IP' => '8.8.8.8'])
            ->assertOk()
            ->assertJsonPath('status', 'warming');

        $this->assertDatabaseHas('ip_intelligence_cache', ['ip' => '203.0.113.99']);
        $this->assertDatabaseMissing('ip_intelligence_cache', ['ip' => '8.8.8.8']);
        Queue::assertPushed(AnalyzeIpIntelligence::class, fn ($job) => $job->ip === '203.0.113.99');
    }

    public function test_an_ipv6_visitor_ip_is_normalized_for_the_cache_key(): void
    {
        $this->visit('2001:4860:4860:0000:0000:0000:0000:8888')
            ->assertOk()
            ->assertJsonPath('status', 'warming');

        $this->assertDatabaseHas('ip_intelligence_cache', ['ip' => '2001:4860:4860::8888', 'ip_version' => 6]);
    }

    // ------------------------------------------- provider key / metrics surface

    public function test_truthy_provider_flags_and_mobile_networks_raise_the_risk(): void
    {
        $this->visit(self::IP)->assertJsonPath('status', 'warming');

        $ipapi = $this->ipapiPayload();
        // ipapi.is documents is_vpn as truthy rather than strictly boolean.
        $ipapi['is_vpn'] = 'NordVPN';
        $ipapi['is_mobile'] = true;

        $this->fakeProviders(['https://api.ipapi.is/*' => Http::response($ipapi)]);

        $this->runAnalysisJob(self::IP);

        $data = $this->storedData(self::IP);

        $this->assertTrue($data['is_vpn']);
        $this->assertSame('mobile', $data['classification']);
        $this->assertGreaterThanOrEqual(18, $data['risk_score']);
        $this->assertStringContainsString('vpn', strtolower(implode(' ', $data['reasons'])));
    }

    public function test_missing_provider_keys_mark_that_provider_unavailable(): void
    {
        config([
            'traceless.ip_intelligence.providers.proxycheck.key' => '',
            'traceless.ip_intelligence.providers.abuseipdb.key' => '',
            'traceless.ip_intelligence.providers.ip2location.key' => '',
        ]);

        $this->visit(self::IP)->assertJsonPath('status', 'warming');

        Http::fake(['https://api.ipapi.is/*' => Http::response($this->ipapiPayload())]);

        $this->runAnalysisJob(self::IP);

        $data = $this->storedData(self::IP);

        $this->assertSame('ok', $data['provider_status']['ipapi_is']);
        $this->assertSame('unavailable', $data['provider_status']['proxycheck']);
        $this->assertSame('unavailable', $data['provider_status']['abuseipdb']);
        $this->assertSame('unavailable', $data['provider_status']['ip2location']);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('ip_intelligence_metrics', ['metric' => 'provider.proxycheck.unavailable', 'value' => 1]);
    }

    public function test_the_metrics_snapshot_covers_every_admin_counter(): void
    {
        $this->visit(self::IP)->assertJsonPath('status', 'warming');
        $this->fakeProviders();
        $this->runAnalysisJob(self::IP);
        $this->visit(self::IP)->assertJsonPath('status', 'ready');

        $snapshot = app(IpIntelligenceMetrics::class)->snapshot();

        $this->assertSame(1, $snapshot['total_analyzed']);
        $this->assertSame(1, $snapshot['fresh_scans']);
        $this->assertSame(1, $snapshot['cache_hits']);
        $this->assertSame(1, $snapshot['cache_misses']);
        $this->assertSame(1, $snapshot['cached_ips']);
        $this->assertSame(0, $snapshot['failed_jobs']);
        $this->assertSame(4, array_sum($snapshot['provider_calls']));
        $this->assertSame(0, array_sum($snapshot['provider_errors']));
        $this->assertSame(0, array_sum($snapshot['rate_limit_events']));
        $this->assertNotNull($snapshot['average_analysis_ms']);
        $this->assertArrayHasKey('ipapi_is', $snapshot['provider_calls']);
        $this->assertArrayHasKey('abuseipdb', $snapshot['provider_errors']);
    }
}
