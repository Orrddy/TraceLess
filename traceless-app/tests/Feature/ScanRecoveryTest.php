<?php

namespace Tests\Feature;

use App\Jobs\RunScanJob;
use App\Livewire\Credentials\CredentialExposures;
use App\Models\IdentityProfile;
use App\Models\Report;
use App\Models\Scan;
use App\Models\User;
use App\Services\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class ScanRecoveryTest extends TestCase
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

    private function scan(IdentityProfile $profile, array $attributes = []): Scan
    {
        return Scan::create(array_merge([
            'identity_profile_id' => $profile->id,
            'type' => 'snapshot',
            'status' => 'queued',
            'progress_percentage' => 0,
            'current_stage' => 'Queued for public-source scan',
            'demo_mode' => false,
        ], $attributes));
    }

    public function test_a_failed_job_marks_the_scan_failed(): void
    {
        $scan = $this->scan($this->profile(), ['status' => 'checking_sources']);

        (new RunScanJob($scan))->failed(new RuntimeException('worker killed'));

        $scan->refresh();

        $this->assertSame('failed', $scan->status);
        $this->assertNotNull($scan->completed_at);
        $this->assertStringContainsString('retry', strtolower((string) $scan->error_message));
    }

    public function test_a_failed_job_does_not_touch_a_completed_scan(): void
    {
        $scan = $this->scan($this->profile(), ['status' => 'completed']);

        (new RunScanJob($scan))->failed(new RuntimeException('late failure'));

        $this->assertSame('completed', $scan->fresh()->status);
    }

    public function test_the_reaper_closes_only_stale_scans(): void
    {
        $profile = $this->profile();

        $stale = $this->scan($profile, ['status' => 'checking_sources']);
        $stale->forceFill(['updated_at' => now()->subMinutes(30)])->saveQuietly();

        $fresh = $this->scan($profile, ['status' => 'checking_sources']);

        $this->artisan('scans:reap-stale')->assertExitCode(0);

        $this->assertSame('failed', $stale->fresh()->status);
        $this->assertSame('checking_sources', $fresh->fresh()->status);
    }

    public function test_the_reaper_leaves_terminal_scans_alone(): void
    {
        $profile = $this->profile();
        $done = $this->scan($profile, ['status' => 'completed']);
        $done->forceFill(['updated_at' => now()->subDays(2)])->saveQuietly();

        $this->artisan('scans:reap-stale')->assertExitCode(0);

        $this->assertSame('completed', $done->fresh()->status);
    }

    public function test_a_stale_scan_no_longer_blocks_the_account(): void
    {
        $profile = $this->profile();
        $stale = $this->scan($profile, ['status' => 'checking_sources']);
        $stale->forceFill(['updated_at' => now()->subMinutes(30)])->saveQuietly();

        $this->assertNull($profile->activeScan());
    }

    public function test_a_fresh_scan_still_blocks_a_second_one(): void
    {
        $profile = $this->profile();
        $this->scan($profile, ['status' => 'checking_sources']);

        $this->assertNotNull($profile->activeScan());
    }

    public function test_the_report_download_url_is_signed(): void
    {
        $profile = $this->profile();

        $report = Report::create([
            'identity_profile_id' => $profile->id,
            'title' => 'Snapshot privacy report',
            'report_type' => 'snapshot',
            'privacy_score' => 80,
            'total_findings' => 0,
            'summary' => '{}',
            'generated_at' => now(),
            'demo_mode' => false,
            'download_token' => str_repeat('a', 48),
        ]);

        $url = $report->downloadUrl();

        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('token=', $url);
    }

    public function test_the_credentials_page_does_not_claim_the_account_is_clear(): void
    {
        config(['traceless.demo_mode' => false, 'traceless.breach.provider' => null]);

        $profile = $this->profile();

        Livewire::actingAs($profile->user)
            ->test(CredentialExposures::class)
            ->assertDontSee('clear of active public credential')
            ->assertSee('not configured');
    }
}
