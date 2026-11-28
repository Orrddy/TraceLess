<?php

namespace Tests\Feature;

use App\Livewire\QuickScan;
use App\Models\QuickScanRecord;
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
            ['Facebook', 'not_found', null],
            ['Instagram', 'found', 'https://instagram.com/adalovelace'],
            ['X', 'found', 'https://x.com/adalovelace'],
            ['LinkedIn', 'not_found', null],
            ['TikTok', 'not_found', null],
            ['YouTube', 'not_found', null],
            ['Reddit', 'not_found', null],
            ['Telegram', 'unknown', null],
            ['Snapchat', 'not_found', null],
            ['Pinterest', 'not_found', null],
        ];

        return [
            'findings' => [
                [
                    'source' => 'Instagram',
                    'category' => 'social_profile',
                    'identifier_type' => 'username',
                    'identifier_value' => 'adalovelace',
                    'url' => 'https://instagram.com/adalovelace',
                    'confidence' => 0.95,
                    'risk' => 'low',
                    'removal_method' => 'manual_instructions',
                    'status' => 'needs_user_action',
                    'title' => 'Public profile on GitHub',
                    'verification_status' => 'verified',
                ],
                [
                    'source' => 'X',
                    'category' => 'social_profile',
                    'identifier_type' => 'username',
                    'identifier_value' => 'adalovelace',
                    'url' => 'https://x.com/adalovelace',
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

    private function emailReport(): array
    {
        $sources = [
            ['Facebook', 'found'],
            ['Instagram', 'found'],
            ['X', 'unknown'],
            ['Pinterest', 'not_found'],
        ];

        return [
            'findings' => [],
            'sources' => collect($sources)->map(fn (array $source) => [
                'name' => $source[0],
                'category' => 'public_directory',
                'outcome' => $source[1],
                'checked_url' => null,
                'http_status' => null,
                'rule' => 'account_probe',
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
            ->assertSee('10 public source checks')
            ->assertSee('no account required');
    }

    public function test_quick_scan_requires_name_email_username_and_consent(): void
    {
        Livewire::test(QuickScan::class)
            ->call('scan')
            ->assertHasErrors(['name', 'email', 'username', 'consent']);
    }

    public function test_quick_scan_returns_ten_source_results_and_keeps_only_an_admin_audit_record(): void
    {
        $mock = Mockery::mock(PythonScannerClient::class);
        $mock->shouldReceive('scanReport')
            ->twice()
            ->andReturnUsing(function (string $type, string $value, bool $consent, string $mode, int $timeout, ?int $limit = null) {
                return $type === 'email' ? $this->emailReport() : $this->report();
            });
        $this->app->instance(PythonScannerClient::class, $mock);

        Livewire::test(QuickScan::class)
            ->set('name', 'Ada')
            ->set('email', 'ada@example.com')
            ->set('username', '@adalovelace')
            ->set('consent', true)
            ->call('scan')
            ->assertHasNoErrors()
            ->assertSet('scanned', true)
            ->assertSee('4 public matches')
            ->assertSee('adalovelace')
            ->assertSee('10 sources checked')
            ->assertSee('2 username')
            ->assertSee('2 email')
            ->assertSee('Instagram')
            ->assertSee('Email registered')
            ->assertSee('Not available')
            ->assertSee('No public match')
            ->assertSee('Could not verify');

        $this->assertDatabaseCount('findings', 0);
        $this->assertDatabaseCount('scans', 0);
        $this->assertDatabaseCount('quick_scan_records', 1);

        $record = QuickScanRecord::first();
        $this->assertSame('Ada', $record->name);
        $this->assertSame('ada@example.com', $record->email);
        $this->assertSame('adalovelace', $record->username);
        $this->assertContains('Instagram', $record->green_sources);
        $this->assertContains('X', $record->green_sources);
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
