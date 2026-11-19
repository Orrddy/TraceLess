<div class="space-y-7">
    <x-page-header
        eyebrow="Administration"
        title="Users"
        description="Accounts, roles and suspension. Every action is written to the audit log.">
        <a href="{{ route('admin.overview') }}" class="btn-secondary text-xs">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            <span>Overview</span>
        </a>
    </x-page-header>

    @if($errors->any())
        <div class="rounded-control border border-danger/30 bg-danger/[0.07] p-4 text-sm text-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="card p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label for="search" class="label mb-1.5">Search</label>
                <input id="search" type="search" wire:model.live.debounce.400ms="search" class="input"
                       placeholder="Name, email or account id" />
            </div>
            <div class="sm:w-48">
                <label for="role" class="label mb-1.5">Role</label>
                <select id="role" wire:model.live="roleFilter" class="input">
                    <option value="">All roles</option>
                    @foreach($roles as $role)
                        <option value="{{ $role }}">{{ str($role)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table text-sm">
                <thead>
                    <tr>
                        <th class="px-5 py-3 text-left">Account</th>
                        <th class="px-5 py-3 text-left">Role</th>
                        <th class="px-5 py-3 text-left">Status</th>
                        <th class="px-5 py-3 text-left">Profiles</th>
                        <th class="px-5 py-3 text-left">Joined</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td class="px-5 py-3">
                                <span class="block text-ink">{{ $user->name }}</span>
                                <span class="block text-[11px] text-faint">{{ $user->email }}</span>
                            </td>
                            <td class="px-5 py-3">
                                @if($canManageRoles && ! $user->is(auth()->user()))
                                    <select wire:change="changeRole({{ $user->id }}, $event.target.value)"
                                            class="input !w-36 !py-1 !text-xs">
                                        @foreach($roles as $role)
                                            <option value="{{ $role }}" @selected($user->role === $role)>
                                                {{ str($role)->replace('_', ' ')->title() }}
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <span class="badge {{ $user->isAdmin() ? 'badge-amber' : 'badge-low' }}">{{ $user->roleLabel() }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @if($user->isSuspended())
                                    <span class="badge badge-critical">Suspended</span>
                                @else
                                    <span class="badge badge-emerald">Active</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 font-mono text-muted">{{ $user->identity_profiles_count }}</td>
                            <td class="px-5 py-3 text-[11px] text-faint">{{ $user->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-5 py-3 text-right">
                                @if($user->is(auth()->user()))
                                    <span class="text-[11px] text-faint">This is you</span>
                                @elseif($user->isSuspended())
                                    <button type="button" wire:click="activate({{ $user->id }})"
                                            wire:confirm="Reactivate {{ $user->email }}?"
                                            class="btn-secondary !px-3 !py-1.5 !text-xs">Reactivate</button>
                                @else
                                    <button type="button" wire:click="suspend({{ $user->id }})"
                                            wire:confirm="Suspend {{ $user->email }}? They keep their data but cannot sign in."
                                            class="btn-secondary !px-3 !py-1.5 !text-xs">Suspend</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-muted">No accounts match this filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
            <div class="border-t border-line px-5 py-3">{{ $users->links() }}</div>
        @endif
    </div>

    <p class="text-[11px] leading-relaxed text-faint">
        Role changes require a super admin. Nobody can suspend or change their own account, and an admin
        cannot act on a super admin. These rules are enforced in the component, not only in the interface.
    </p>
</div>
