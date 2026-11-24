<?php

namespace Tests\Feature;

use App\Jobs\RunScanJob;
use App\Livewire\Scans\ScanProgress;
use App\Mail\DeepScanStartedMail;
use App\Models\IdentityProfile;
use App\Models\Scan;
use App\Models\User;
use App\Notifications\ScanCompletedNotification;
use App\Services\ConsentService;
use App\Services\ScanOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ScanConsoleTest extends TestCase
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

    public function test_a_deep_scan_queues_the_started_email_with_a_two_hour_estimate(): void
    {
        Queue::fake();
        Mail::fake();
        config(['traceless.demo_mode' => false]);

        $profile = $this->profile();
        $scan = app(ScanOrchestrator::class)->queueScan($profile, 'deep_scan');

        $this->assertSame(7200, $scan->estimatedDurationSeconds());
        $this->assertNotNull($scan->estimatedCompletionAt());
        $this->assertEqualsWithDelta(
            $scan->created_at->getTimestamp() + 7200,
            $scan->estimatedCompletionAt()->getTimestamp(),
            2,
        );

        Mail::assertQueued(DeepScanStartedMail::class, fn (DeepScanStartedMail $mail) => $mail->scan->id === $scan->id);
    }

    public function test_the_scan_status_endpoint_returns_absolute_server_timestamps(): void
    {
        $profile = $this->profile();
        $scan = Scan::create([
            'identity_profile_id' => $profile->id,
            'type' => 'deep_scan',
            'status' => 'checking_sources',
            'progress_percentage' => 42,
            'current_stage' => 'Checking public sources',
            'sources_checked' => 12,
            'sources_total' => 60,
            'findings_count' => 3,
        ]);

        $response = $this->actingAs($profile->user)->getJson(route('scans.status', $scan));

        $response->assertOk()
            ->assertJsonPath('id', $scan->id)
            ->assertJsonPath('progress', 42)
            ->assertJsonPath('is_active', true)
            ->assertJsonPath('deadline_unix_ms', $scan->estimatedCompletionAt()->getTimestampMs());

        $this->assertNotEmpty($response->json('server_now_unix_ms'));
    }

    public function test_the_scan_console_renders_server_timestamps_for_the_countdown(): void
    {
        $profile = $this->profile();
        $scan = Scan::create([
            'identity_profile_id' => $profile->id,
            'type' => 'deep_scan',
            'status' => 'checking_sources',
            'progress_percentage' => 15,
            'current_stage' => 'Checking public sources',
        ]);

        Livewire::actingAs($profile->user)
            ->test(ScanProgress::class, ['scan' => $scan])
            ->assertSee('data-scan-fx', false)
            ->assertSee('EST. TIME REMAINING', false)
            ->assertSee('data-deadline-ms="'.$scan->estimatedCompletionAt()->getTimestampMs().'"', false);
    }

    public function test_a_completed_scan_shows_its_results_link_instead_of_the_countdown(): void
    {
        $profile = $this->profile();
        $scan = Scan::create([
            'identity_profile_id' => $profile->id,
            'type' => 'deep_scan',
            'status' => 'completed',
            'progress_percentage' => 100,
            'current_stage' => 'Completed',
            'findings_count' => 7,
            'started_at' => now()->subMinutes(3),
            'completed_at' => now(),
        ]);

        $scan->forceFill(['created_at' => now()->subHours(3)])->save();
        $scan->refresh();

        Livewire::actingAs($profile->user)
            ->test(ScanProgress::class, ['scan' => $scan])
            ->assertSee('Sweep complete')
            ->assertSee('7 public records found')
            ->assertSee('Open results');
    }

    public function test_a_deep_scan_that_finished_early_keeps_counting_and_hides_results(): void
    {
        $profile = $this->profile();
        $scan = Scan::create([
            'identity_profile_id' => $profile->id,
            'type' => 'deep_scan',
            'status' => 'completed',
            'progress_percentage' => 100,
            'current_stage' => 'Scan complete',
            'findings_count' => 7,
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
        ]);

        $this->assertTrue($scan->isHoldingResults());
        $this->assertTrue($scan->isPresentedActive());

        Livewire::actingAs($profile->user)
            ->test(ScanProgress::class, ['scan' => $scan])
            ->assertSee('Still processing — high traffic')
            ->assertSee('RESULTS ARRIVING IN')
            ->assertSee('High traffic is slowing the final assembly')
            ->assertDontSee('Open results');
    }

    public function test_the_deep_scan_budget_allows_two_hours(): void
    {
        $this->assertSame(7200, (int) config('traceless.scan.deep_wall_clock_seconds'));
        $this->assertSame(7600, (new RunScanJob(new Scan))->timeout);
        $this->assertGreaterThanOrEqual(8100, (int) config('queue.connections.database.retry_after'));
    }

    public function test_scans_use_a_random_reference_in_urls_and_keep_numeric_links_working(): void
    {
        $profile = $this->profile();
        $scan = Scan::create([
            'identity_profile_id' => $profile->id,
            'type' => 'snapshot',
            'status' => 'queued',
        ]);

        $this->assertMatchesRegularExpression('/^TR-[A-Z0-9]{10}$/', $scan->reference());
        $this->assertSame($scan->public_id, $scan->getAttribute($scan->getRouteKeyName()));
        $this->assertStringContainsString($scan->public_id, route('scans.show', $scan));

        $this->actingAs($profile->user)->get(route('scans.show', $scan))->assertOk();
        $this->actingAs($profile->user)->get('/scans/'.$scan->id)->assertOk();
    }

    public function test_the_scan_completion_email_is_branded_and_has_no_default_footer_copy(): void
    {
        $profile = $this->profile();
        $scan = Scan::create([
            'identity_profile_id' => $profile->id,
            'type' => 'snapshot',
            'status' => 'completed',
            'progress_percentage' => 100,
            'findings_count' => 3,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);

        $mail = (new ScanCompletedNotification($scan))->toMail($profile->user);
        $this->assertSame('emails.scan-complete', $mail->view);

        $html = view('emails.scan-complete', [
            'scan' => $scan,
            'reference' => $scan->reference(),
            'identityName' => $profile->full_name,
            'findings' => 3,
            'score' => 70,
            'url' => route('scans.show', $scan),
            'logoUrl' => asset('apple-touch-icon.png'),
            'wordmarkUrl' => asset('brand/wordmark-email.png'),
        ])->render();

        $this->assertStringContainsString('brand/wordmark-email.png', $html);
        $this->assertStringContainsString($scan->reference(), $html);
        $this->assertStringNotContainsString('copy and paste', strtolower($html));
    }

    public function test_the_dashboard_ships_the_branded_page_loader(): void
    {
        $profile = $this->profile();

        $this->actingAs($profile->user)->get('/dashboard')->assertSee('data-page-loader', false);
    }
}
