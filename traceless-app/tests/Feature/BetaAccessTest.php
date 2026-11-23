<?php

namespace Tests\Feature;

use App\Jobs\DispatchDueMonitoringScans;
use App\Livewire\Monitoring\MonitoringPage;
use App\Livewire\Reports\ReportsPage;
use App\Livewire\Scans\ScanCreate;
use App\Models\IdentityProfile;
use App\Models\MonitoringSubscription;
use App\Models\Report;
use App\Models\Scan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\ScanOrchestrator;
use App\Support\BetaAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class BetaAccessTest extends TestCase
{
    use RefreshDatabase;

    private function profileFor(User $user): IdentityProfile
    {
        $profile = IdentityProfile::create([
            'user_id' => $user->id,
            'full_name' => 'Beta Tester',
            'primary_email' => $user->email,
            'consent_acknowledged' => true,
            'consent_given_at' => now(),
        ]);

        app(ConsentService::class)->recordConsent($user, $profile, '127.0.0.1');

        $profile->publicIdentifiers()->create([
            'type' => 'username',
            'value' => 'betatester',
            'is_monitored' => true,
        ]);

        return $profile;
    }

    private function planlessUser(): array
    {
        $user = User::factory()->create();
        $profile = $this->profileFor($user);

        return [$user, $profile];
    }

    private function deepScanSubscription(User $user): Subscription
    {
        return Subscription::create([
            'user_id' => $user->id,
            'plan' => 'deep_scan',
            'status' => 'active',
            'provider' => 'demo',
        ]);
    }

    public function test_the_free_beta_flag_defaults_on_and_disables_billing(): void
    {
        config(['traceless.beta' => true]);

        $this->assertTrue(BetaAccess::enabled());
        $this->assertFalse(BetaAccess::billingEnabled());

        $user = User::factory()->create();
        $this->assertTrue($user->isDeepScan());
        $this->assertFalse(BetaAccess::grantsDeepScan(null));
    }

    public function test_a_planless_account_can_queue_a_deep_scan_during_the_beta(): void
    {
        Queue::fake();
        config(['traceless.beta' => true]);
        [$user, $profile] = $this->planlessUser();

        Livewire::actingAs($user)->test(ScanCreate::class)
            ->set('profile_id', $profile->id)
            ->set('type', 'deep_scan')
            ->set('consent_confirmed', true)
            ->call('start')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('scans', ['identity_profile_id' => $profile->id, 'type' => 'deep_scan']);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('billing_records', 0);
    }

    public function test_a_planless_account_can_enable_recurring_monitoring_during_the_beta(): void
    {
        config(['traceless.beta' => true, 'traceless.demo_mode' => false]);
        [$user, $profile] = $this->planlessUser();

        Livewire::actingAs($user)->test(MonitoringPage::class)
            ->set('isActive', true)
            ->set('cadence', 'weekly')
            ->call('save')
            ->assertHasNoErrors();

        $subscription = MonitoringSubscription::where('identity_profile_id', $profile->id)->firstOrFail();
        $this->assertTrue((bool) $subscription->is_active);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('billing_records', 0);
    }

    public function test_monitoring_dispatch_runs_for_planless_accounts_during_the_beta(): void
    {
        Queue::fake();
        config(['traceless.beta' => true, 'traceless.monitoring.enforce_plan' => true]);
        [$user, $profile] = $this->planlessUser();

        MonitoringSubscription::create([
            'identity_profile_id' => $profile->id,
            'is_active' => true,
            'cadence' => 'weekly',
            'next_scan_at' => now()->subDay(),
        ]);

        (new DispatchDueMonitoringScans)->handle(app(ScanOrchestrator::class));

        $this->assertSame(1, Scan::where('identity_profile_id', $profile->id)->where('type', 'deep_scan')->count());
    }

    public function test_a_planless_account_can_generate_a_deep_report_during_the_beta(): void
    {
        config(['traceless.beta' => true]);
        [$user, $profile] = $this->planlessUser();

        Livewire::actingAs($user)->test(ReportsPage::class)
            ->set('type', 'deep_scan')
            ->call('generate')
            ->assertHasNoErrors();

        $this->assertSame(1, Report::where('identity_profile_id', $profile->id)->where('report_type', 'deep_scan')->count());
        $this->assertDatabaseCount('billing_records', 0);
    }

    public function test_with_the_beta_off_previous_plan_gates_return(): void
    {
        Queue::fake();
        config(['traceless.beta' => false, 'traceless.monitoring.enforce_plan' => true, 'traceless.demo_mode' => false]);
        [$user, $profile] = $this->planlessUser();

        $this->assertFalse($user->isDeepScan());
        $this->assertTrue(BetaAccess::billingEnabled());

        Livewire::actingAs($user)->test(ScanCreate::class)
            ->set('profile_id', $profile->id)
            ->set('type', 'deep_scan')
            ->set('consent_confirmed', true)
            ->call('start')
            ->assertHasErrors('type');

        $this->assertSame(0, Scan::count());

        Livewire::actingAs($user)->test(MonitoringPage::class)
            ->set('isActive', true)
            ->call('save')
            ->assertHasErrors('isActive');

        MonitoringSubscription::create([
            'identity_profile_id' => $profile->id,
            'is_active' => true,
            'cadence' => 'weekly',
            'next_scan_at' => now()->subDay(),
        ]);

        (new DispatchDueMonitoringScans)->handle(app(ScanOrchestrator::class));

        $this->assertSame(0, Scan::count());
    }

    public function test_with_the_beta_off_an_active_plan_still_unlocks_everything(): void
    {
        Queue::fake();
        config(['traceless.beta' => false]);
        [$user, $profile] = $this->planlessUser();
        $this->deepScanSubscription($user);

        $this->assertTrue($user->fresh()->isDeepScan());
        $this->assertDatabaseCount('billing_records', 0);

        Livewire::actingAs($user)->test(ScanCreate::class)
            ->set('profile_id', $profile->id)
            ->set('type', 'deep_scan')
            ->set('consent_confirmed', true)
            ->call('start')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('scans', ['identity_profile_id' => $profile->id, 'type' => 'deep_scan']);
    }
}
