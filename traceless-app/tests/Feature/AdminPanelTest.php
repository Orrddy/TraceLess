<?php

namespace Tests\Feature;

use App\Livewire\Admin\ActivityLog;
use App\Livewire\Admin\Overview;
use App\Livewire\Admin\UsersIndex;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Finding;
use App\Models\IdentityProfile;
use App\Models\LoginEvent;
use App\Models\RemovalTask;
use App\Models\Scan;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role = User::ROLE_CLIENT, bool $suspended = false): User
    {
        return User::factory()->create([
            'role' => $role,
            'suspended_at' => $suspended ? now() : null,
        ]);
    }

    private function profileFor(User $user): IdentityProfile
    {
        return IdentityProfile::create([
            'user_id' => $user->id,
            'full_name' => 'Test Person',
            'primary_email' => $user->email,
            'consent_acknowledged' => true,
            'consent_given_at' => now(),
        ]);
    }

    // ------------------------------------------------------------ access control

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/admin/users')->assertRedirect('/login');
        $this->get('/admin/activity')->assertRedirect('/login');
    }

    public function test_client_accounts_cannot_reach_any_admin_page(): void
    {
        $client = $this->account();

        $this->actingAs($client)->get('/admin')->assertForbidden();
        $this->actingAs($client)->get('/admin/users')->assertForbidden();
        $this->actingAs($client)->get('/admin/activity')->assertForbidden();
    }

    public function test_admins_and_super_admins_can_reach_every_admin_page(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN] as $role) {
            $admin = $this->account($role);

            $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Overview');
            $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee('Users');
            $this->actingAs($admin)->get('/admin/activity')->assertOk()->assertSee('Activity log');
        }
    }

    public function test_a_suspended_admin_is_blocked(): void
    {
        $admin = $this->account(User::ROLE_ADMIN, suspended: true);

        $this->actingAs($admin)->get('/admin')->assertForbidden();
    }

    public function test_a_suspended_client_is_blocked_from_the_client_app(): void
    {
        $client = $this->account(suspended: true);

        $this->actingAs($client)->get('/dashboard')->assertForbidden();
    }

    public function test_admin_navigation_is_only_rendered_for_admins(): void
    {
        $client = $this->account();
        $admin = $this->account(User::ROLE_ADMIN);

        $clientView = $this->actingAs($client)->get('/dashboard');
        $clientView->assertOk();
        $clientView->assertDontSee('/admin/users');

        $adminView = $this->actingAs($admin)->get('/dashboard');
        $adminView->assertOk();
        $adminView->assertSee('/admin/users');
    }

    // ---------------------------------------------------------------- dashboard

    public function test_the_dashboard_reports_real_database_counts(): void
    {
        $admin = $this->account(User::ROLE_SUPER_ADMIN);
        $client = $this->account();
        $profile = $this->profileFor($client);

        $scan = Scan::create([
            'identity_profile_id' => $profile->id,
            'type' => 'snapshot',
            'status' => 'completed',
            'progress_percentage' => 100,
            'current_stage' => 'Completed',
            'demo_mode' => false,
        ]);

        Finding::create([
            'identity_profile_id' => $profile->id,
            'scan_id' => $scan->id,
            'source' => 'GitHub',
            'category' => 'developer_profile',
            'identifier_type' => 'username',
            'identifier_value' => 'tester',
            'confidence' => 0.9,
            'risk' => 'low',
            'removal_method' => 'manual_instructions',
            'status' => 'needs_user_action',
            'title' => 'Public profile on GitHub',
            'discovered_at' => now(),
            'last_checked_at' => now(),
        ]);

        RemovalTask::create([
            'identity_profile_id' => $profile->id,
            'finding_id' => Finding::where('identity_profile_id', $profile->id)->value('id'),
            'source_name' => 'GitHub',
            'status' => 'ready_to_remove',
            'workflow_type' => 'manual',
            'estimated_turnaround' => '1-7 days',
        ]);

        Alert::create([
            'identity_profile_id' => $profile->id,
            'type' => 'new_exposure',
            'severity' => 'moderate',
            'title' => 'New record',
            'message' => 'A record appeared.',
            'is_read' => false,
        ]);

        Livewire::actingAs($admin)->test(Overview::class)
            ->assertViewHas('stats', function (array $stats) use ($client) {
                return $stats['users_total'] === 2
                    && $stats['admins_total'] === 1
                    && $stats['profiles_total'] === 1
                    && $stats['scans_total'] === 1
                    && $stats['scans_today'] === 1
                    && $stats['findings_total'] === 1
                    && $stats['removals_total'] === 1
                    && $stats['alerts_unread'] === 1
                    && $stats['queue_pending'] === 0
                    && $stats['queue_failed'] === 0
                    && $client->isAdmin() === false;
            })
            ->assertSee('Test Person');
    }

    // -------------------------------------------------------------- user actions

    public function test_an_admin_can_suspend_and_reactivate_a_client(): void
    {
        $admin = $this->account(User::ROLE_ADMIN);
        $client = $this->account();

        Livewire::actingAs($admin)->test(UsersIndex::class)
            ->call('suspend', $client->id)
            ->assertHasNoErrors();

        $this->assertNotNull($client->fresh()->suspended_at);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'admin.user_suspended',
        ]);

        Livewire::actingAs($admin)->test(UsersIndex::class)
            ->call('activate', $client->id);

        $this->assertNull($client->fresh()->suspended_at);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'admin.user_activated',
        ]);
    }

    public function test_an_admin_cannot_change_roles(): void
    {
        $admin = $this->account(User::ROLE_ADMIN);
        $client = $this->account();

        // Component methods are called directly here: Livewire's test harness
        // converts aborts into HTTP responses, so the exception only surfaces
        // as it would in production when the method itself is invoked.
        $this->actingAs($admin);

        try {
            (new UsersIndex)->changeRole($client->id, User::ROLE_ADMIN, app(AuditService::class));
            $this->fail('An admin was able to change a role.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame(User::ROLE_CLIENT, $client->fresh()->role);
    }

    public function test_a_super_admin_can_promote_and_demote_an_account(): void
    {
        $super = $this->account(User::ROLE_SUPER_ADMIN);
        $client = $this->account();

        Livewire::actingAs($super)->test(UsersIndex::class)
            ->call('changeRole', $client->id, User::ROLE_ADMIN)
            ->assertHasNoErrors();

        $this->assertSame(User::ROLE_ADMIN, $client->fresh()->role);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.role_changed']);

        Livewire::actingAs($super)->test(UsersIndex::class)
            ->call('changeRole', $client->id, User::ROLE_CLIENT);

        $this->assertSame(User::ROLE_CLIENT, $client->fresh()->role);
    }

    public function test_nobody_can_suspend_or_demote_themselves(): void
    {
        $super = $this->account(User::ROLE_SUPER_ADMIN);

        Livewire::actingAs($super)->test(UsersIndex::class)
            ->call('suspend', $super->id)
            ->assertHasErrors('action');

        $this->assertNull($super->fresh()->suspended_at);

        Livewire::actingAs($super)->test(UsersIndex::class)
            ->call('changeRole', $super->id, User::ROLE_CLIENT)
            ->assertHasErrors('action');

        $this->assertSame(User::ROLE_SUPER_ADMIN, $super->fresh()->role);
    }

    public function test_an_admin_cannot_suspend_a_super_admin(): void
    {
        $admin = $this->account(User::ROLE_ADMIN);
        $super = $this->account(User::ROLE_SUPER_ADMIN);

        Livewire::actingAs($admin)->test(UsersIndex::class)
            ->call('suspend', $super->id)
            ->assertHasErrors('action');

        $this->assertNull($super->fresh()->suspended_at);
    }

    public function test_a_client_cannot_invoke_admin_actions_through_livewire(): void
    {
        $client = $this->account();
        $victim = $this->account();

        $this->actingAs($client);

        try {
            (new UsersIndex)->suspend($victim->id, app(AuditService::class));
            $this->fail('A client invoked an admin action.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertNull($victim->fresh()->suspended_at);
    }

    public function test_clients_cannot_render_any_admin_component_directly(): void
    {
        $client = $this->account();
        $this->actingAs($client);

        foreach ([Overview::class, UsersIndex::class, ActivityLog::class] as $component) {
            try {
                // Mount is what Livewire calls when the component is first
                // instantiated through /livewire/update, before any render.
                (new $component)->mount();
                $this->fail("A client mounted {$component}.");
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode(), $component);
            }
        }
    }

    public function test_the_user_list_searches_and_filters(): void
    {
        $admin = $this->account(User::ROLE_ADMIN);
        $alice = User::factory()->create(['name' => 'Alice Finder', 'email' => 'alice@example.com']);
        $bob = User::factory()->create(['name' => 'Bob Other', 'email' => 'bob@example.com', 'role' => User::ROLE_ADMIN]);

        Livewire::actingAs($admin)->test(UsersIndex::class)
            ->assertSee('Alice Finder')
            ->assertSee('Bob Other')
            ->set('search', 'alice')
            ->assertSee('Alice Finder')
            ->assertDontSee('Bob Other')
            ->set('search', '')
            ->set('roleFilter', User::ROLE_ADMIN)
            ->assertSee('Bob Other')
            ->assertDontSee('Alice Finder');

        $this->assertNotNull($bob);
    }

    // -------------------------------------------------------------- activity log

    public function test_the_activity_log_shows_and_filters_audit_entries(): void
    {
        $admin = $this->account(User::ROLE_SUPER_ADMIN);
        $client = $this->account();

        AuditLog::create(['user_id' => $client->id, 'action' => 'login', 'details' => 'First event', 'ip_address' => '10.0.0.1']);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'admin.role_changed', 'details' => 'Second event', 'ip_address' => '10.0.0.2']);

        Livewire::actingAs($admin)->test(ActivityLog::class)
            ->assertSee('First event')
            ->assertSee('Second event')
            ->set('actionFilter', 'admin.role_changed')
            ->assertSee('Second event')
            ->assertDontSee('First event')
            ->set('actionFilter', '')
            ->set('search', '10.0.0.1')
            ->assertSee('First event')
            ->assertDontSee('Second event')
            ->call('clearFilters')
            ->assertSee('First event');
    }

    // ------------------------------------------------------------ provisioning

    public function test_the_promote_command_grants_admin_and_logs_it(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $exit = Artisan::call('admin:promote', ['email' => 'owner@example.com', '--role' => User::ROLE_SUPER_ADMIN]);

        $this->assertSame(0, $exit);
        $this->assertSame(User::ROLE_SUPER_ADMIN, $user->fresh()->role);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.provisioned']);
    }

    public function test_the_promote_command_rejects_unknown_accounts_and_roles(): void
    {
        $this->assertSame(1, Artisan::call('admin:promote', ['email' => 'nobody@example.com']));

        $user = User::factory()->create(['email' => 'owner@example.com']);
        $this->assertSame(1, Artisan::call('admin:promote', ['email' => 'owner@example.com', '--role' => 'root']));
        $this->assertSame(User::ROLE_CLIENT, $user->fresh()->role);
    }

    public function test_the_revoke_command_keeps_the_last_administrator(): void
    {
        $only = User::factory()->create(['email' => 'only@example.com', 'role' => User::ROLE_SUPER_ADMIN]);

        $this->assertSame(1, Artisan::call('admin:revoke', ['email' => 'only@example.com']));
        $this->assertSame(User::ROLE_SUPER_ADMIN, $only->fresh()->role);

        $second = User::factory()->create(['email' => 'second@example.com', 'role' => User::ROLE_ADMIN]);
        $this->assertSame(0, Artisan::call('admin:revoke', ['email' => 'second@example.com']));
        $this->assertSame(User::ROLE_CLIENT, $second->fresh()->role);
    }

    public function test_new_accounts_default_to_the_client_role(): void
    {
        $this->assertSame(User::ROLE_CLIENT, User::factory()->create()->role);
    }

    public function test_client_functionality_still_works_for_an_active_account(): void
    {
        $client = $this->account();
        $this->profileFor($client);

        $this->actingAs($client)->get('/dashboard')->assertOk();
        $this->actingAs($client)->get('/settings')->assertOk();
        $this->actingAs($client)->get('/identity')->assertOk();
    }

    // ------------------------------------------- richer account detail controls

    public function test_the_users_table_shows_scan_count_sign_in_ip_device_and_monitoring(): void
    {
        $admin = $this->account(User::ROLE_ADMIN);
        $client = $this->account();
        $profile = $this->profileFor($client);

        $scan = Scan::create([
            'identity_profile_id' => $profile->id,
            'type' => 'deep_scan',
            'status' => 'completed',
            'progress_percentage' => 100,
            'findings_count' => 2,
        ]);

        $profile->monitoringSubscription()->create([
            'is_active' => true,
            'cadence' => 'weekly',
            'next_scan_at' => now()->addWeek(),
        ]);

        LoginEvent::create([
            'user_id' => $client->id,
            'ip_address' => '203.0.113.10',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0 Safari/537.36',
            'device' => 'Chrome · Windows',
            'logged_in_at' => now(),
        ]);

        Livewire::actingAs($admin)->test(UsersIndex::class)
            ->assertSee('203.0.113.10')
            ->assertSee('Chrome · Windows')
            ->call('toggleDetails', $client->id)
            ->assertSee($scan->reference())
            ->assertSee('Scan activity')
            ->assertSee('Pause');
    }

    public function test_an_admin_can_pause_and_resume_an_accounts_monitoring(): void
    {
        $admin = $this->account(User::ROLE_ADMIN);
        $client = $this->account();
        $profile = $this->profileFor($client);
        $subscription = $profile->monitoringSubscription()->create([
            'is_active' => true,
            'cadence' => 'weekly',
            'next_scan_at' => now()->addWeek(),
        ]);

        Livewire::actingAs($admin)->test(UsersIndex::class)
            ->call('toggleMonitoring', $client->id)
            ->assertHasNoErrors();

        $this->assertFalse($subscription->fresh()->is_active);
        $this->assertNull($subscription->fresh()->next_scan_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.monitoring_paused']);

        Livewire::actingAs($admin)->test(UsersIndex::class)
            ->call('toggleMonitoring', $client->id)
            ->assertHasNoErrors();

        $this->assertTrue($subscription->fresh()->is_active);
        $this->assertNotNull($subscription->fresh()->next_scan_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.monitoring_resumed']);
    }

    public function test_an_admin_can_mark_an_account_pending_or_verified(): void
    {
        $admin = $this->account(User::ROLE_ADMIN);
        $client = $this->account();

        Livewire::actingAs($admin)->test(UsersIndex::class)
            ->call('markPending', $client->id)
            ->assertHasNoErrors();

        $this->assertNull($client->fresh()->email_verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.user_pending_verification']);

        Livewire::actingAs($admin)->test(UsersIndex::class)
            ->call('markVerified', $client->id)
            ->assertHasNoErrors();

        $this->assertNotNull($client->fresh()->email_verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.user_verified']);
    }

    public function test_a_pending_account_cannot_use_the_client_app(): void
    {
        $pending = User::factory()->unverified()->create();

        $this->actingAs($pending)->get('/dashboard')->assertForbidden();
        $this->assertGuest();
    }
}
