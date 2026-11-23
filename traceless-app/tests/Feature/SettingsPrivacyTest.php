<?php

namespace Tests\Feature;

use App\Livewire\Settings\SettingsPage;
use App\Mail\OtpCodeMail;
use App\Models\Alert;
use App\Models\CredentialExposure;
use App\Models\Finding;
use App\Models\IdentityProfile;
use App\Models\RemovalTask;
use App\Models\Report;
use App\Models\Scan;
use App\Models\User;
use App\Services\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function populatedUser(): array
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);
        $profile = IdentityProfile::create([
            'user_id' => $user->id,
            'full_name' => 'Jane Doe',
            'primary_email' => 'jane@example.com',
            'consent_acknowledged' => true,
            'consent_given_at' => now(),
        ]);
        app(ConsentService::class)->recordConsent($user, $profile);

        $scan = Scan::create([
            'identity_profile_id' => $profile->id,
            'type' => 'snapshot',
            'status' => 'completed',
            'progress_percentage' => 100,
            'current_stage' => 'Done',
            'completed_at' => now(),
        ]);
        $finding = Finding::create([
            'identity_profile_id' => $profile->id,
            'scan_id' => $scan->id,
            'source' => 'Whitepages',
            'category' => 'data_broker',
            'identifier_type' => 'username',
            'identifier_value' => 'janedoe',
            'risk' => 'critical',
            'status' => 'ready_to_remove',
            'title' => 'Listing',
        ]);
        RemovalTask::create([
            'identity_profile_id' => $profile->id,
            'finding_id' => $finding->id,
            'source_name' => $finding->source,
            'status' => 'ready_to_remove',
        ]);
        CredentialExposure::create([
            'identity_profile_id' => $profile->id,
            'email' => 'jane@example.com',
            'service_name' => 'Example breach',
            'risk_level' => 'high',
        ]);
        Alert::create([
            'identity_profile_id' => $profile->id,
            'type' => 'new_exposure',
            'severity' => 'high',
            'title' => 'New listing',
            'message' => 'Something changed.',
        ]);
        Report::create([
            'identity_profile_id' => $profile->id,
            'title' => 'Snapshot',
            'report_type' => 'snapshot',
            'privacy_score' => 70,
            'total_findings' => 1,
            'download_token' => 'token-'.uniqid(),
        ]);

        return [$user, $profile];
    }

    protected function otpCodeFor(string $email): string
    {
        $code = null;

        Mail::assertSent(OtpCodeMail::class, function (OtpCodeMail $mail) use (&$code, $email) {
            if (! $mail->hasTo($email)) {
                return false;
            }

            $code = $mail->code;

            return true;
        });

        $this->assertNotNull($code, "No OTP email was sent to {$email}.");

        return $code;
    }

    public function test_purge_scan_data_removes_artifacts_but_keeps_account(): void
    {
        [$user, $profile] = $this->populatedUser();
        $this->actingAs($user);

        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\AlertNotification',
            'data' => ['title' => 'Old alert', 'message' => 'A record appeared.'],
        ]);

        Livewire::test(SettingsPage::class)
            ->set('confirm_data_purge', true)
            ->call('purgeScanData');

        $this->assertDatabaseMissing('findings', ['identity_profile_id' => $profile->id]);
        $this->assertDatabaseMissing('scans', ['identity_profile_id' => $profile->id]);
        $this->assertDatabaseMissing('reports', ['identity_profile_id' => $profile->id]);
        $this->assertDatabaseMissing('credential_exposures', ['identity_profile_id' => $profile->id]);
        $this->assertDatabaseMissing('removal_tasks', ['identity_profile_id' => $profile->id]);
        $this->assertDatabaseMissing('alerts', ['identity_profile_id' => $profile->id]);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseHas('identity_profiles', ['id' => $profile->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_purge_requires_confirmation(): void
    {
        [$user] = $this->populatedUser();
        $this->actingAs($user);

        Livewire::test(SettingsPage::class)
            ->set('confirm_data_purge', false)
            ->call('purgeScanData')
            ->assertHasErrors('confirm_data_purge');

        $this->assertDatabaseCount('findings', 1);
    }

    public function test_account_deletion_requires_an_email_code(): void
    {
        Mail::fake();
        [$user] = $this->populatedUser();
        $this->actingAs($user);
        $userId = $user->id;

        Livewire::test(SettingsPage::class)
            ->call('requestDeleteCode')
            ->assertHasNoErrors()
            ->assertSet('delete_code_sent', true);

        Livewire::test(SettingsPage::class)
            ->set('delete_code', '000000')
            ->set('confirm_account_delete', true)
            ->call('deleteAccount')
            ->assertHasErrors('delete_code');

        $this->assertDatabaseHas('users', ['id' => $userId]);

        Livewire::test(SettingsPage::class)
            ->set('delete_code', $this->otpCodeFor($user->email))
            ->set('confirm_account_delete', true)
            ->call('deleteAccount')
            ->assertRedirect('/')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('audit_logs', ['action' => 'account_deleted']);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
        $this->assertDatabaseMissing('identity_profiles', ['user_id' => $userId]);
        $this->assertGuest();
    }

    public function test_data_export_returns_json_attachment_without_password_fields(): void
    {
        [$user] = $this->populatedUser();

        $response = $this->actingAs($user)->get(route('account.export'));

        $response->assertOk();
        $response->assertHeader('Content-Disposition');
        $response->assertJsonPath('account.email', $user->email);
        $response->assertJsonPath('profiles.0.full_name', 'Jane Doe');
        $this->assertStringNotContainsString('password', strtolower($response->getContent()));
    }

    public function test_email_change_requires_a_code_sent_to_the_new_address(): void
    {
        Mail::fake();
        [$user] = $this->populatedUser();
        $this->actingAs($user);

        $component = Livewire::test(SettingsPage::class)
            ->set('name', 'Jane Updated')
            ->set('email', 'new@example.com')
            ->call('updateProfile')
            ->assertHasNoErrors()
            ->assertSet('email_change_sent', true)
            ->assertSet('pending_email', 'new@example.com');

        // The address does not change until the new inbox is verified.
        $this->assertSame('jane@example.com', $user->fresh()->email);
        $this->assertSame('Jane Updated', $user->fresh()->name);

        $component
            ->set('email_change_code', $this->otpCodeFor('new@example.com'))
            ->call('confirmEmailChange')
            ->assertHasNoErrors();

        $this->assertSame('new@example.com', $user->fresh()->email);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'email_changed']);
    }

    public function test_an_email_change_otp_outage_is_a_form_error(): void
    {
        [$user] = $this->populatedUser();
        $this->actingAs($user);

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 2,
            'mail.mailers.smtp.timeout' => 1,
        ]);

        Livewire::test(SettingsPage::class)
            ->set('name', 'Jane Doe')
            ->set('email', 'new@example.com')
            ->call('updateProfile')
            ->assertHasErrors('email');

        $this->assertSame('jane@example.com', $user->fresh()->email);
    }
}
