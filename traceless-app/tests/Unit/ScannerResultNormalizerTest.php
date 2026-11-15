<?php

namespace Tests\Unit;

use App\Services\ScannerResultNormalizer;
use InvalidArgumentException;
use Tests\TestCase;

class ScannerResultNormalizerTest extends TestCase
{
    protected ScannerResultNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new ScannerResultNormalizer;
    }

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'status' => 'success',
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
                    'title' => 'Public profile on GitHub',
                    'verification_status' => 'verified',
                    'evidence' => [
                        'rule' => 'e_string',
                        'http_status' => 200,
                        'checked_url' => 'https://github.com/janedoe',
                        'body_sha256' => str_repeat('a', 64),
                        'elapsed_ms' => 240,
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
            ],
        ], $overrides);
    }

    public function test_it_normalizes_a_verified_report(): void
    {
        $result = $this->normalizer->normalize($this->payload(), 'username', 'janedoe');

        $this->assertCount(1, $result['findings']);
        $this->assertCount(1, $result['sources']);

        $finding = $result['findings'][0];
        $this->assertSame('GitHub', $finding['source']);
        $this->assertSame('verified', $finding['verification_status']);
        $this->assertSame(200, $finding['evidence']['http_status']);
        $this->assertSame('found', $result['sources'][0]['outcome']);
    }

    public function test_it_rejects_non_success_payloads(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->normalizer->normalize(['status' => 'error'], 'username', 'janedoe');
    }

    public function test_it_drops_rows_without_a_source_name(): void
    {
        $payload = $this->payload(['findings' => [['url' => 'https://example.test']]]);

        $result = $this->normalizer->normalize($payload, 'username', 'janedoe');

        $this->assertSame([], $result['findings']);
    }

    public function test_it_defaults_unknown_values_to_safe_choices(): void
    {
        $payload = $this->payload([
            'findings' => [[
                'source' => 'Reddit',
                'category' => 'not-a-category',
                'risk' => 'apocalyptic',
                'removal_method' => 'telepathy',
                'verification_status' => 'trust-me',
                'confidence' => 4.7,
            ]],
            'sources' => [['name' => 'Reddit', 'outcome' => 'maybe', 'http_status' => 9000]],
        ]);

        $finding = $this->normalizer->normalize($payload, 'username', 'janedoe')['findings'][0];
        $this->assertSame('social_profile', $finding['category']);
        $this->assertSame('moderate', $finding['risk']);
        $this->assertSame('manual_instructions', $finding['removal_method']);
        $this->assertSame('unverified', $finding['verification_status']);
        $this->assertEquals(1.0, $finding['confidence']);

        $source = $this->normalizer->normalize($payload, 'username', 'janedoe')['sources'][0];
        $this->assertSame('unknown', $source['outcome']);
        $this->assertNull($source['http_status']);
    }

    public function test_it_rejects_non_http_urls_and_bad_hashes(): void
    {
        $payload = $this->payload([
            'findings' => [[
                'source' => 'GitHub',
                'url' => 'javascript:alert(1)',
                'evidence' => [
                    'checked_url' => 'file:///etc/passwd',
                    'body_sha256' => 'not-a-hash',
                    'http_status' => '200',
                ],
            ]],
        ]);

        $finding = $this->normalizer->normalize($payload, 'username', 'janedoe')['findings'][0];

        $this->assertNull($finding['url']);
        $this->assertArrayNotHasKey('checked_url', $finding['evidence']);
        $this->assertArrayNotHasKey('body_sha256', $finding['evidence']);
        $this->assertSame(200, $finding['evidence']['http_status']);
    }

    public function test_it_strips_html_and_caps_long_text(): void
    {
        $payload = $this->payload([
            'findings' => [[
                'source' => 'GitHub',
                'title' => '<script>alert(1)</script>'.str_repeat('x', 400),
                'description' => '<b>bold</b> text',
            ]],
        ]);

        $finding = $this->normalizer->normalize($payload, 'username', 'janedoe')['findings'][0];

        $this->assertStringNotContainsString('<script>', $finding['title']);
        $this->assertLessThanOrEqual(180, mb_strlen($finding['title']));
        $this->assertSame('bold text', $finding['description']);
    }

    public function test_it_falls_back_to_the_requested_identifier(): void
    {
        $payload = $this->payload([
            'findings' => [['source' => 'GitHub']],
        ]);

        $finding = $this->normalizer->normalize($payload, 'username', 'janedoe')['findings'][0];

        $this->assertSame('username', $finding['identifier_type']);
        $this->assertSame('janedoe', $finding['identifier_value']);
    }
}
