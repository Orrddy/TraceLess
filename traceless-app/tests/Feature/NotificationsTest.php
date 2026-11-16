<?php

namespace Tests\Feature;

use App\Livewire\Notifications\NotificationsPanel;
use App\Models\Alert;
use App\Models\IdentityProfile;
use App\Models\Scan;
use App\Models\User;
use App\Notifications\AlertNotification;
use App\Notifications\ScanCompletedNotification;
use App\Services\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithAlert(bool $read = false): array
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

        $alert = Alert::create([
            'identity_profile_id' => $profile->id,
            'type' => 'new_exposure',
            'severity' => 'moderate',
            'title' => 'New public record found',
            'message' => 'A record appeared on Example.',
            'action_url' => 'https://example.test/record',
            'is_read' => $read,
        ]);

        return [$user, $alert];
    }

    public function test_notifications_reach_the_user_by_email_as_well_as_in_app(): void
    {
        [$user, $alert] = $this->ownerWithAlert();

        $notification = new AlertNotification($alert);

        $this->assertContains('mail', $notification->via($user));
        $this->assertContains('database', $notification->via($user));

        $mail = $notification->toMail($user);

        $this->assertSame('New public record found', $mail->subject);
        $this->assertNotEmpty($mail->actionUrl);
    }

    public function test_the_scan_completion_notification_has_a_subject_and_action(): void
    {
        [$user, $alert] = $this->ownerWithAlert();
        $scan = Scan::create([
            'identity_profile_id' => $alert->identity_profile_id,
            'type' => 'snapshot',
            'status' => 'completed',
            'progress_percentage' => 100,
            'current_stage' => 'Completed',
            'demo_mode' => false,
        ]);

        $mail = (new ScanCompletedNotification($scan))->toMail($user);

        $this->assertSame('Your TraceLess scan is complete', $mail->subject);
        $this->assertStringContainsString('Scan #'.$scan->id, $mail->introLines[0]);
    }

    public function test_the_panel_is_mounted_in_the_app_shell(): void
    {
        [$user] = $this->ownerWithAlert();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('aria-label="Notifications"', false);
    }

    public function test_the_panel_lists_and_dismisses_an_alert(): void
    {
        [$user, $alert] = $this->ownerWithAlert();

        $component = Livewire::actingAs($user)->test(NotificationsPanel::class)
            ->assertSee('New public record found')
            ->call('markRead', $alert->id);

        $this->assertTrue($alert->fresh()->is_read);
    }

    public function test_the_panel_marks_everything_read(): void
    {
        [$user] = $this->ownerWithAlert();

        Livewire::actingAs($user)->test(NotificationsPanel::class)->call('markAllRead');

        $this->assertSame(0, Alert::where('is_read', false)->count());
    }
}
