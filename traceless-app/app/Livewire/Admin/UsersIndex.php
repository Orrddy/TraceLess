<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
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

    public function suspend(int $id, AuditService $audit): void
    {
        $actor = Auth::user();
        abort_unless($actor && $actor->isAdmin(), 403);

        $target = User::findOrFail($id);

        if ($target->is($actor)) {
            $this->addError('action', 'You cannot suspend your own account.');

            return;
        }

        if ($target->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            $this->addError('action', 'Only a super admin can suspend another super admin.');

            return;
        }

        $target->update(['suspended_at' => now()]);

        $audit->log(
            $actor->id,
            'admin.user_suspended',
            "Suspended user #{$target->id} ({$target->role}).",
            request()->ip(),
        );

        $this->dispatch('notify', message: 'Account suspended.');
    }

    public function activate(int $id, AuditService $audit): void
    {
        $actor = Auth::user();
        abort_unless($actor && $actor->isAdmin(), 403);

        $target = User::findOrFail($id);
        $target->update(['suspended_at' => null]);

        $audit->log(
            $actor->id,
            'admin.user_activated',
            "Reactivated user #{$target->id} ({$target->role}).",
            request()->ip(),
        );

        $this->dispatch('notify', message: 'Account reactivated.');
    }

    public function changeRole(int $id, string $role, AuditService $audit): void
    {
        $actor = Auth::user();
        abort_unless($actor && $actor->isSuperAdmin(), 403);

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

        $audit->log(
            $actor->id,
            'admin.role_changed',
            "User #{$target->id}: {$previous} -> {$role}.",
            request()->ip(),
        );

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
            ->withCount('identityProfiles')
            ->latest()
            ->paginate($this->perPage);

        return view('livewire.admin.users-index', [
            'users' => $users,
            'roles' => User::ROLES,
            'canManageRoles' => (bool) Auth::user()?->isSuperAdmin(),
        ])->layout('layouts.app', ['title' => 'Admin users — TraceLess']);
    }
}
