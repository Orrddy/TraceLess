<?php

namespace Tests\Feature;

use App\Livewire\Identity\IdentitySetup;
use App\Models\ConsentRecord;
use App\Models\IdentityProfile;
use App\Models\PublicIdentifier;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\IpReputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class IdentityConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_identity_setup_requires_consent(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(IdentitySetup::class)
            ->set('names', ['Jane Doe'])
            ->set('primary_email', 'jane@example.com')
            ->set('consent_confirmed', false)
            ->call('saveAndScan')
            ->assertHasErrors(['consent_confirmed']);

        $this->assertDatabaseCount('identity_profiles', 0);
    }

    public function test_identity_setup_creates_profile_consent_and_identifiers(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(IdentitySetup::class)
            ->set('names', ['Jane Doe', 'Jane Smith'])
            ->set('primary_email', 'jane@example.com')
            ->set('phone_number', '+44 20 7946 0000')
            ->set('usernames', ['janedoe'])
            ->set('consent_confirmed', true)
            ->set('start_scan', false)
            ->call('saveAndScan')
            ->assertRedirect(route('identity'));

        $profile = IdentityProfile::firstOrFail();
        $this->assertSame($user->id, $profile->user_id);
        $this->assertTrue($profile->consent_acknowledged);
        $this->assertNotNull($profile->consent_given_at);

        $this->assertDatabaseHas('consent_records', [
            'user_id' => $user->id,
            'identity_profile_id' => $profile->id,
            'consent_text' => ConsentService::REQUIRED_CONSENT_TEXT,
        ]);

        $this->assertTrue(PublicIdentifier::where('identity_profile_id', $profile->id)->where('type', 'email')->exists());
        $this->assertSame('Jane Doe', $profile->full_name);
        $this->assertSame('Jane Smith', $profile->previous_names);
        $this->assertTrue(PublicIdentifier::where('identity_profile_id', $profile->id)->where('value', 'janedoe')->exists());
    }

    public function test_names_and_usernames_can_be_added_and_removed(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(IdentitySetup::class)
            ->set('name_input', 'Jane Doe')->call('addName')
            ->set('name_input', 'Jane Smith')->call('addName')
            ->assertSet('names', ['Jane Doe', 'Jane Smith'])
            ->call('removeName', 0)
            ->assertSet('names', ['Jane Smith'])
            ->set('username_input', '@janedoe')->call('addUsername')
            ->assertSet('usernames', ['janedoe'])
            ->call('removeUsername', 0)
            ->assertSet('usernames', []);
    }

    public function test_get_my_ip_fills_the_detected_address(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $mock = Mockery::mock(IpReputationService::class);
        $mock->shouldReceive('lookup')->once()->andReturn(['ip' => '41.203.72.10', 'auto' => true]);
        $this->app->instance(IpReputationService::class, $mock);

        Livewire::test(IdentitySetup::class)
            ->call('detectIp')
            ->assertSet('detected_ip', '41.203.72.10')
            ->assertSet('ip_error', null);
    }

    public function test_consent_service_detects_missing_and_valid_consent(): void
    {
        $user = User::factory()->create();
        $profile = IdentityProfile::create([
            'user_id' => $user->id,
            'full_name' => 'Jane Doe',
            'primary_email' => 'jane@example.com',
        ]);

        $consent = app(ConsentService::class);
        $this->assertFalse($consent->hasValidConsent($profile));

        $consent->recordConsent($user, $profile, '127.0.0.1');
        $this->assertTrue($consent->hasValidConsent($profile->fresh()));

        $consent->revoke($profile);
        $this->assertFalse($consent->hasValidConsent($profile->fresh()));
    }

    public function test_a_user_cannot_belong_to_more_than_one_private_profile_view(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $profile = IdentityProfile::create([
            'user_id' => $owner->id,
            'full_name' => 'Owner Identity',
            'primary_email' => 'owner@example.com',
            'consent_acknowledged' => true,
            'consent_given_at' => now(),
        ]);
        ConsentRecord::create([
            'user_id' => $owner->id,
            'identity_profile_id' => $profile->id,
            'consent_text' => 'consent',
            'agreed_at' => now(),
        ]);

        $this->actingAs($other)->get('/identity')->assertOk();
        $this->assertFalse($other->identityProfiles()->whereKey($profile->id)->exists());
    }
}
