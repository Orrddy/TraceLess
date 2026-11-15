<?php

namespace Tests\Feature;

use App\Livewire\Identity\IdentitySetup;
use App\Models\ConsentRecord;
use App\Models\IdentityProfile;
use App\Models\PublicIdentifier;
use App\Models\User;
use App\Services\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IdentityConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_identity_setup_requires_consent(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(IdentitySetup::class)
            ->set('full_name', 'Jane Doe')
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
            ->set('full_name', 'Jane Doe')
            ->set('primary_email', 'jane@example.com')
            ->set('phone_number', '+44 20 7946 0000')
            ->set('region', 'London, UK')
            ->set('aliases', 'jdoe')
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
        $this->assertTrue(PublicIdentifier::where('identity_profile_id', $profile->id)->where('value', 'janedoe')->exists());
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
