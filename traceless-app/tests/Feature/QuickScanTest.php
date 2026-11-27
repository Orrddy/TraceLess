<?php

namespace Tests\Feature;

use App\Livewire\QuickScan;
use App\Services\PythonScannerClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class QuickScanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function report(): array
    {
        $sources = [
            ['GitHub', 'found', 'https://github.com/adalovelace'],
            ['GitLab', 'not_found', null],
            ['Reddit', 'found', 'https://reddit.com/user/adalovelace'],
            ['X', 'unknown', null],
            ['Instagram', 'not_found', null],
            ['LinkedIn', 'skipped', null],
            ['Stack Overflow', 'not_found', null],
            ['Gravatar', 'unknown', null],
            ['Medium', 'not_found', null],
            ['DeviantArt', 'not_found', null],
        ];

        return [
            'findings' => [
                [
                    'source' => 'GitHub',
                    'category' => 'developer_profile',
                    'identifier_type' => 'username',
                    'identifier_value' => 'adalovelace',
                    'url' => 'https://github.com/adalovelace',
                    'confidence' => 0.95,
                    'risk' => 'low',
                    'removal_method' => 'manual_instructions',
                    'status' => 'needs_user_action',
                    'title' => 'Public profile on GitHub',
                    'verification_status' => 'verified',
                ],
                [
                    'source' => 'Reddit',
                    'category' => 'social_profile',
                    'identifier_type' => 'username',
                    'identifier_value' => 'adalovelace',
                    'url' => 'https://reddit.com/user/adalovelace',
                    'confidence' => 0.8,
                    'risk' => 'moderate',
                    'removal_method' => 'manual_instructions',
                    'status' => 'needs_user_action',
                    'title' => 'Public profile on Reddit',
                    'verification_status' => 'verified',
                ],
            ],
            'sources' => collect($sources)->map(fn (array $source) => [
                'name' => $source[0],
                'category' => 'social_profile',
                'outcome' => $source[1],
                'checked_url' => $source[2],
                'http_status' => null,
                'rule' => null,
                'reason' => $source[1] === 'unknown' ? 'source_blocked_request' : null,
                'elapsed_ms' => 100,
            ])->all(),
        ];
    }

    public function test_guests_can_open_the_quick_scan_page(): void
    {
        $this->get('/quick-scan')
            ->assertOk()
            ->assertSee('Free quick check')
            ->assertSee('10 public profile checks')
            ->assertSee('no account required');
    }

    public function test_quick_scan_requires_name_email_username_and_consent(): void
    {
        Livewire::test(QuickScan::class)
            ->call('scan')
            ->assertHasErrors(['name', 'email', 'username', 'consent']);
    }

    public function test_quick_scan_returns_ten_source_results_without_persisting_them(): void
    {
        $mock = Mockery::mock(PythonScannerClient::class);
        $mock->shouldReceive('scanReport')
            ->once()
            ->with('username', 'adalovelace', true, 'curated', 45, 10)
            ->andReturn($this->report());
        $this->app->instance(PythonScannerClient::class, $mock);

        Livewire::test(QuickScan::class)
            ->set('name', 'Ada')
            ->set('email', 'ada@example.com')
            ->set('username', '@adalovelace')
            ->set('consent', true)
            ->call('scan')
            ->assertHasNoErrors()
            ->assertSet('scanned', true)
            ->assertSee('2 public matches')
            ->assertSee('adalovelace')
            ->assertSee('10 sources checked')
            ->assertSee('GitHub')
            ->assertSee('No public match')
            ->assertSee('Could not verify');

        $this->assertDatabaseCount('findings', 0);
        $this->assertDatabaseCount('scans', 0);
        $this->assertSame('Ada', session('quick_scan_prefill.name'));
    }

    public function test_honeypot_fills_do_not_run_a_scan_or_consume_the_quota(): void
    {
        $mock = Mockery::mock(PythonScannerClient::class);
        $mock->shouldNotReceive('scanReport');
        $this->app->instance(PythonScannerClient::class, $mock);

        Livewire::test(QuickScan::class)
            ->set('name', 'Ada')
            ->set('email', 'ada@example.com')
            ->set('username', 'adalovelace')
            ->set('consent', true)
            ->set('website', 'https://spam.example')
            ->call('scan')
            ->assertSee('could not run');
    }
}
