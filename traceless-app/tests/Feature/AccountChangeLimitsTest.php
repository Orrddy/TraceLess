<?php

namespace Tests\Feature;

use App\Livewire\Monitoring\MonitoringPage;
use App\Livewire\Settings\SettingsPage;
use App\Models\Alert;
use App\Models\IdentityProfile;
use App\Models\User;
use App\Services\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class AccountChangeLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_code_requests_are_limited_per_user(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $component = Livewire::actingAs($user)->test(SettingsPage::class);

        // The first request succeeds; the per-address cooldown answers the next
        // two, but every call still counts against the limiter.
        for ($i = 0; $i < 3; $i++) {
            $component->call('requestDeleteCode');
        }

        $component->call('requestDeleteCode');

        $errors = implode(' | ', $component->errors()->all());

        $this->assertStringContainsString('Too many code requests', $errors);
    }

    public function test_the_limit_is_per_user(): void
    {
        $first = User::factory()->create(['email' => 'first@example.com']);
        $second = User::factory()->create(['email' => 'second@example.com']);

        $a = Livewire::actingAs($first)->test(SettingsPage::class);
        for ($i = 0; $i < 3; $i++) {
            $a->call('requestDeleteCode');
        }

        Livewire::actingAs($second)->test(SettingsPage::class)
            ->call('requestDeleteCode')
            ->assertSet('delete_code_sent', true);
    }

    public function test_email_change_requests_are_limited_per_user(): void
    {
        $user = User::factory()->create(['name' => 'Jane', 'email' => 'jane@example.com']);

        $component = Livewire::actingAs($user)->test(SettingsPage::class)
            ->set('name', 'Jane')
            ->set('email', 'new@example.com');

        for ($i = 0; $i < 3; $i++) {
            $component->call('updateProfile');
        }

        $component->call('updateProfile');

        $this->assertStringContainsString('Too many code requests', (string) $component->errors()->first('email'));
    }

    public function test_an_alert_belongs_only_to_its_owner(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $profile = IdentityProfile::create([
            'user_id' => $owner->id,
            'full_name' => 'Jane Doe',
            'primary_email' => 'jane@example.com',
            'consent_acknowledged' => true,
            'consent_given_at' => now(),
        ]);
        app(ConsentService::class)->recordConsent($owner, $profile, '127.0.0.1');

        $alert = Alert::create([
            'identity_profile_id' => $profile->id,
            'type' => 'new_exposure',
            'severity' => 'moderate',
            'title' => 'New public record',
            'message' => 'A new record was found.',
            'is_read' => false,
        ]);

        $this->assertTrue(Gate::forUser($owner)->allows('update', $alert));
        $this->assertFalse(Gate::forUser($stranger)->allows('update', $alert));
    }

    public function test_marking_someone_elses_alert_read_is_refused(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $profile = IdentityProfile::create([
            'user_id' => $owner->id,
            'full_name' => 'Jane Doe',
            'primary_email' => 'jane@example.com',
            'consent_acknowledged' => true,
            'consent_given_at' => now(),
        ]);
        app(ConsentService::class)->recordConsent($owner, $profile, '127.0.0.1');

        $alert = Alert::create([
            'identity_profile_id' => $profile->id,
            'type' => 'new_exposure',
            'severity' => 'moderate',
            'title' => 'New public record',
            'message' => 'A new record was found.',
            'is_read' => false,
        ]);

        Livewire::actingAs($stranger)->test(MonitoringPage::class)
            ->call('markAlertRead', $alert->id);

        $this->assertFalse($alert->fresh()->is_read, 'A stranger must not be able to mark an alert read.');

        Livewire::actingAs($owner)->test(MonitoringPage::class)
            ->call('markAlertRead', $alert->id);

        $this->assertTrue($alert->fresh()->is_read, 'The owner must be able to mark their own alert read.');
    }
}
