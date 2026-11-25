<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * User administration.
 *
 * The route middleware blocks clients from loading the page, but Livewire calls
 * arrive through /livewire/update and skip route middleware. Every mutating
 * method therefore re-checks the acting user's role itself.
 */
class UsersIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $roleFilter = '';

    public int $perPage = 15;

    public ?int $expandedUserId = null;

    public function mount(): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    public function toggleDetails(int $id): void
    {
        $this->expandedUserId = $this->expandedUserId === $id ? null : $id;
    }

    public function suspend(int $id, AuditService $audit): void
    {
        $actor = $this->actor();
        $target = User::findOrFail($id);

        if (! $this->mayManageAccount($actor, $target)) {
            return;
        }

        $target->update(['suspended_at' => now()]);

        $audit->log($actor->id, 'admin.user_suspended', "Disabled user #{$target->id} ({$target->role}).", request()->ip());

        $this->dispatch('notify', message: 'Account disabled.');
    }

    public function activate(int $id, AuditService $audit): void
    {
        $actor = $this->actor();
        $target = User::findOrFail($id);

        if (! $this->mayManageAccount($actor, $target)) {
            return;
        }

        $target->update(['suspended_at' => null]);

        $audit->log($actor->id, 'admin.user_activated', "Enabled user #{$target->id} ({$target->role}).", request()->ip());

        $this->dispatch('notify', message: 'Account enabled.');
    }

    public function markPending(int $id, AuditService $audit): void
    {
        $actor = $this->actor();
        $target = User::findOrFail($id);

        if (! $this->mayManageAccount($actor, $target)) {
            return;
        }

        $target->forceFill([
            'email_verified_at' => null,
            'suspended_at' => null,
        ])->save();

        $audit->log($actor->id, 'admin.user_pending_verification', "Set user #{$target->id} to pending verification.", request()->ip());

        $this->dispatch('notify', message: 'Account set to pending verification; it will be signed out on the next request.');
    }

    public function markVerified(int $id, AuditService $audit): void
    {
        $actor = $this->actor();
        $target = User::findOrFail($id);

        if (! $this->mayManageAccount($actor, $target)) {
            return;
        }

        $target->forceFill([
            'email_verified_at' => now(),
            'suspended_at' => null,
        ])->save();

        $audit->log($actor->id, 'admin.user_verified', "Verified user #{$target->id}.", request()->ip());

        $this->dispatch('notify', message: 'Account verified and enabled.');
    }

    public function toggleMonitoring(int $id, AuditService $audit): void
    {
        $actor = $this->actor();
        $target = User::findOrFail($id);
        $profile = $target->identityProfiles()->latest()->first();

        if (! $profile) {
            $this->addError('action', 'This account has no identity profile to monitor.');

            return;
        }

        $subscription = $profile->monitoringSubscription;

        if (! $subscription) {
            $this->addError('action', 'This identity has no monitoring schedule yet.');

            return;
        }

        $active = ! $subscription->is_active;
        $subscription->update([
            'is_active' => $active,
            'next_scan_at' => $active
                ? $this->nextScanFor($subscription->cadence)
                : null,
        ]);

        $audit->log(
            $actor->id,
            $active ? 'admin.monitoring_resumed' : 'admin.monitoring_paused',
            ($active ? 'Resumed' : 'Paused')." monitoring for user #{$target->id}.",
            request()->ip(),
        );

        $this->dispatch('notify', message: $active ? 'Monitoring resumed.' : 'Monitoring paused.');
    }

    public function changeRole(int $id, string $role, AuditService $audit): void
    {
        $actor = $this->actor();

        if (! $actor->isSuperAdmin()) {
            abort(403);
        }

        if (! in_array($role, User::ROLES, true)) {
            $this->addError('action', 'Unknown role.');

            return;
        }

        $target = User::findOrFail($id);

        if ($target->is($actor)) {
            $this->addError('action', 'You cannot change your own role.');

            return;
        }

        if ($target->role === $role) {
            return;
        }

        $previous = $target->role;
        $target->update(['role' => $role]);

        $audit->log($actor->id, 'admin.role_changed', "User #{$target->id}: {$previous} -> {$role}.", request()->ip());

        $this->dispatch('notify', message: 'Role updated.');
    }

    public function render()
    {
        $users = User::query()
            ->when($this->search !== '', function ($query) {
                $term = '%'.trim($this->search).'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)->orWhere('email', 'like', $term);
                });
            })
            ->when($this->roleFilter !== '', fn ($query) => $query->where('role', $this->roleFilter))
            ->withCount(['identityProfiles', 'scans'])
            ->with([
                'identityProfiles' => fn ($query) => $query->latest()->limit(1),
                'identityProfiles.monitoringSubscription',
                'loginEvents' => fn ($query) => $query->latest('logged_in_at')->limit(4),
            ])
            ->latest()
            ->paginate($this->perPage);

        $sessions = DB::table('sessions')
            ->whereIn('user_id', $users->pluck('id'))
            ->where('last_activity', '>=', now()->subDays(30)->timestamp)
            ->orderByDesc('last_activity')
            ->get()
            ->groupBy('user_id');

        return view('livewire.admin.users-index', [
            'users' => $users,
            'sessions' => $sessions,
            'roles' => User::ROLES,
            'canManageRoles' => (bool) Auth::user()?->isSuperAdmin(),
        ])->layout('layouts.app', ['title' => 'Admin users — TraceLess']);
    }

    private function actor(): User
    {
        $actor = Auth::user();
        abort_unless($actor && $actor->isAdmin(), 403);

        return $actor;
    }

    private function mayManageAccount(User $actor, User $target): bool
    {
        if ($target->is($actor)) {
            $this->addError('action', 'You cannot change your own account.');

            return false;
        }

        if ($target->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            $this->addError('action', 'Only a super admin can change another super admin.');

            return false;
        }

        return true;
    }

    private function nextScanFor(?string $cadence): Carbon
    {
        return now()->addDays(match ($cadence) {
            'daily' => 1,
            'monthly' => 30,
            default => 7,
        });
    }
}
