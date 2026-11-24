<?php

namespace Tests\Feature;

use App\Livewire\Scans\ScanProgress;
use App\Mail\DeepScanStartedMail;
use App\Models\IdentityProfile;
use App\Models\Scan;
use App\Models\User;
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

        Livewire::actingAs($profile->user)
            ->test(ScanProgress::class, ['scan' => $scan])
            ->assertSee('Sweep complete')
            ->assertSee('7 public records found')
            ->assertSee('Open results');
    }
}
