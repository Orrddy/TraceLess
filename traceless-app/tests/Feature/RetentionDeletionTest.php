<?php

namespace Tests\Feature;

use App\Livewire\Settings\SettingsPage;
use App\Models\AuditLog;
use App\Models\ConsentRecord;
use App\Models\EmailOtp;
use App\Models\Finding;
use App\Models\IdentityProfile;
use App\Models\Scan;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RetentionDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function profileFor(User $user): IdentityProfile
    {
        $profile = IdentityProfile::create([
            'user_id' => $user->id,
            'full_name' => 'Jane Doe',
            'primary_email' => 'jane@example.com',
            'consent_acknowledged' => true,
            'consent_given_at' => now(),
        ]);

        app(ConsentService::class)->recordConsent($user, $profile, '10.0.0.9');

        return $profile;
    }

    public function test_pruning_an_old_scan_removes_its_newer_findings(): void
    {
        $user = User::factory()->create();
        $profile = $this->profileFor($user);

        $scan = Scan::create([
            'identity_profile_id' => $profile->id,
            'type' => 'snapshot',
            'status' => 'completed',
            'progress_percentage' => 100,
            'current_stage' => 'Completed',
            'demo_mode' => false,
        ]);
        $scan->forceFill(['created_at' => now()->subDays(400)])->saveQuietly();

        // The finding is newer than the retention cutoff, which used to leave it
        // orphaned forever once the scan was deleted.
        $finding = Finding::create([
            'identity_profile_id' => $profile->id,
            'scan_id' => $scan->id,
            'source' => 'GitHub',
            'category' => 'developer_profile',
            'identifier_type' => 'username',
            'identifier_value' => 'janedoe',
            'confidence' => 0.9,
            'risk' => 'low',
            'removal_method' => 'manual_instructions',
            'status' => 'needs_user_action',
            'title' => 'Public profile on GitHub',
            'discovered_at' => now(),
            'last_checked_at' => now(),
        ]);

        $this->artisan('traceless:prune')->assertExitCode(0);

        $this->assertDatabaseMissing('findings', ['id' => $finding->id]);
        $this->assertDatabaseMissing('scans', ['id' => $scan->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'retention_purge']);
    }

    public function test_pruning_leaves_recent_scans_alone(): void
    {
        $user = User::factory()->create();
        $profile = $this->profileFor($user);

        $scan = Scan::create([
            'identity_profile_id' => $profile->id,
            'type' => 'snapshot',
            'status' => 'completed',
            'progress_percentage' => 100,
            'current_stage' => 'Completed',
            'demo_mode' => false,
        ]);

        $this->artisan('traceless:prune')->assertExitCode(0);

        $this->assertDatabaseHas('scans', ['id' => $scan->id]);
    }

    public function test_deleting_an_account_strips_ip_addresses_from_the_audit_trail(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);
        $this->profileFor($user);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'details' => 'Signed in.',
            'ip_address' => '10.0.0.9',
        ]);

        $otp = app(OtpService::class)->issue($user->email, EmailOtp::PURPOSE_DELETE_ACCOUNT, '10.0.0.9', 'test');

        Livewire::actingAs($user)->test(SettingsPage::class)
            ->set('delete_code', $otp->plainCode)
            ->set('confirm_account_delete', true)
            ->call('deleteAccount');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertSame(0, AuditLog::where('ip_address', '10.0.0.9')->count());
    }

    public function test_deleting_an_account_keeps_proof_that_consent_was_recorded(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);
        $profile = $this->profileFor($user);

        $otp = app(OtpService::class)->issue($user->email, EmailOtp::PURPOSE_DELETE_ACCOUNT, '10.0.0.9', 'test');

        Livewire::actingAs($user)->test(SettingsPage::class)
            ->set('delete_code', $otp->plainCode)
            ->set('confirm_account_delete', true)
            ->call('deleteAccount');

        // The consent row itself cascades with the profile, so its content must be
        // preserved in the audit trail that outlives the account.
        $this->assertSame(0, ConsentRecord::where('identity_profile_id', $profile->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'consent_evidence_retained']);
        $this->assertGreaterThan(0, AuditLog::where('action', 'consent_evidence_retained')->count());
    }
}
