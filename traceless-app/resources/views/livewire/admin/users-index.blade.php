<div class="space-y-7">
    <x-page-header
        eyebrow="Administration"
        title="Users"
        description="Accounts, scan activity, sign-in locations, monitoring controls and account states. Every action is written to the audit log.">
        <a href="{{ route('admin.overview') }}" class="btn-secondary text-xs">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            <span>Overview</span>
        </a>
    </x-page-header>

    @if(isset($errors) && $errors->any())
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
                        <th class="px-5 py-3 text-left">Scans</th>
                        <th class="px-5 py-3 text-left">Monitoring</th>
                        <th class="px-5 py-3 text-left">Last sign-in</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        @php
                            $latestLogin = $user->loginEvents->first();
                            $latestSession = ($sessions->get($user->id) ?? collect())->first();
                            $profile = $user->identityProfiles->first();
                            $monitoring = $profile?->monitoringSubscription;
                            $isPending = $user->email_verified_at === null;
                            $statusLabel = $user->isSuspended() ? 'Disabled' : ($isPending ? 'Pending verification' : 'Active');
                            $statusBadge = $user->isSuspended() ? 'badge-critical' : ($isPending ? 'badge-amber' : 'badge-emerald');
                            $signInIp = $latestLogin?->ip_address ?? $latestSession?->ip_address;
                            $signInDevice = $latestLogin?->device
                                ?? ($latestSession?->user_agent ? \App\Models\LoginEvent::describe($latestSession->user_agent) : null);
                            $canActOn = ! $user->is(auth()->user()) && (! $user->isSuperAdmin() || auth()->user()?->isSuperAdmin());
                        @endphp
                        <tr wire:key="user-row-{{ $user->id }}">
                            <td class="px-5 py-3">
                                <button type="button" wire:click="toggleDetails({{ $user->id }})" class="text-left">
                                    <span class="block font-medium text-ink">{{ $user->name }}</span>
                                    <span class="block text-[11px] text-faint">{{ $user->email }}</span>
                                </button>
                                <span class="mt-1 block font-mono text-[10px] text-faint">
                                    Joined {{ $user->created_at?->format('Y-m-d') }} · {{ $user->identity_profiles_count }} profile{{ $user->identity_profiles_count === 1 ? '' : 's' }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                @if($canManageRoles && $canActOn)
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
                                <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                                @if($user->suspended_at)
                                    <span class="mt-1 block text-[10px] text-faint">since {{ $user->suspended_at->format('Y-m-d') }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 font-mono text-muted">{{ $user->scans_count }}</td>
                            <td class="px-5 py-3">
                                @if($monitoring)
                                    <span class="badge {{ $monitoring->is_active ? 'badge-emerald' : 'badge-neutral' }}">
                                        {{ $monitoring->is_active ? 'Active' : 'Paused' }}
                                    </span>
                                    <button type="button" wire:click="toggleMonitoring({{ $user->id }})"
                                            wire:confirm="{{ $monitoring->is_active ? 'Pause' : 'Resume' }} monitoring for {{ $user->email }}?"
                                            class="mt-1 block text-[11px] font-medium text-accent hover:underline">
                                        {{ $monitoring->is_active ? 'Pause' : 'Resume' }}
                                    </button>
                                @else
                                    <span class="text-[11px] text-faint">No schedule</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @if($user->last_login_at || $signInIp)
                                    <span class="block text-[12px] text-ink">{{ $user->last_login_at?->diffForHumans(short: true) ?? 'Recently' }}</span>
                                    <span class="block font-mono text-[11px] text-muted">{{ $signInIp ?? 'IP not recorded' }}</span>
                                    <span class="block text-[10px] text-faint">{{ $signInDevice ?? 'Device not recorded' }}</span>
                                @else
                                    <span class="text-[11px] text-faint">Never signed in</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex flex-wrap items-center justify-end gap-1.5">
                                    <button type="button" wire:click="toggleDetails({{ $user->id }})"
                                            class="btn-secondary !px-3 !py-1.5 !text-xs">
                                        {{ $expandedUserId === $user->id ? 'Hide' : 'Details' }}
                                    </button>
                                    @if($canActOn)
                                        @if($user->isSuspended())
                                            <button type="button" wire:click="activate({{ $user->id }})"
                                                    wire:confirm="Enable access for {{ $user->email }}?"
                                                    class="btn-secondary !px-3 !py-1.5 !text-xs">Enable</button>
                                        @else
                                            <button type="button" wire:click="suspend({{ $user->id }})"
                                                    wire:confirm="Disable {{ $user->email }}? They keep their data but cannot sign in."
                                                    class="btn-secondary !px-3 !py-1.5 !text-xs">Disable</button>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>

                        @if($expandedUserId === $user->id)
                            @php $latestScan = $user->scans()->latest()->first(); @endphp
                            <tr wire:key="user-detail-{{ $user->id }}" class="bg-elevated/40">
                                <td colspan="7" class="px-5 py-5">
                                    <div class="grid gap-4 lg:grid-cols-3">
                                        <div class="rounded-xl border border-line bg-canvas/60 p-4">
                                            <p class="mono-label">Scan activity</p>
                                            <p class="mt-2 text-2xl font-semibold text-ink">{{ $user->scans_count }}</p>
                                            <p class="text-[11px] text-muted">total scans</p>
                                            @if($latestScan)
                                                <div class="mt-3 space-y-1 border-t border-line pt-3 text-[11px]">
                                                    <p class="font-mono text-ink">{{ $latestScan->reference() }}</p>
                                                    <p class="text-muted">{{ ucfirst(str_replace('_', ' ', $latestScan->type)) }} · {{ $latestScan->statusLabel() }}</p>
                                                    <p class="text-faint">{{ $latestScan->created_at?->diffForHumans() }}</p>
                                                    <a href="{{ route('admin.scans.show', $latestScan) }}" class="inline-block pt-1 font-medium text-accent hover:underline">Inspect latest scan →</a>
                                                </div>
                                            @else
                                                <p class="mt-3 text-[11px] text-faint">No scans yet.</p>
                                            @endif
                                        </div>

                                        <div class="rounded-xl border border-line bg-canvas/60 p-4">
                                            <div class="flex items-center justify-between">
                                                <p class="mono-label">Sign-in locations</p>
                                                <span class="text-[10px] text-faint">Last {{ $user->loginEvents->count() }}</span>
                                            </div>
                                            <div class="mt-2 space-y-2">
                                                @forelse($user->loginEvents as $event)
                                                    <div class="rounded-lg border border-line/70 bg-canvas/80 px-3 py-2">
                                                        <p class="flex items-center justify-between gap-2 text-[11px] font-medium text-ink">
                                                            <span class="font-mono">{{ $event->ip_address ?? 'unknown IP' }}</span>
                                                            <span class="text-[10px] text-faint">{{ $event->logged_in_at?->diffForHumans(short: true) }}</span>
                                                        </p>
                                                        <p class="mt-0.5 text-[10px] text-muted">{{ $event->device ?? \App\Models\LoginEvent::describe($event->user_agent) }}</p>
                                                        @if($event->user_agent)
                                                            <p class="mt-0.5 truncate font-mono text-[9px] text-faint" title="{{ $event->user_agent }}">{{ \Illuminate\Support\Str::limit($event->user_agent, 42) }}</p>
                                                        @endif
                                                    </div>
                                                @empty
                                                    <p class="text-[11px] text-faint">No sign-in history recorded yet.</p>
                                                @endforelse
                                            </div>
                                            @php $activeSessions = $sessions->get($user->id) ?? collect(); @endphp
                                            @if($activeSessions->isNotEmpty())
                                                <p class="mt-3 border-t border-line pt-2 text-[10px] font-medium uppercase tracking-wider text-faint">Active sessions</p>
                                                <div class="mt-1 space-y-1">
                                                    @foreach($activeSessions->take(3) as $session)
                                                        <p class="flex items-center justify-between gap-2 text-[10px] text-muted">
                                                            <span class="font-mono">{{ $session->ip_address }}</span>
                                                            <span>{{ \App\Models\LoginEvent::describe($session->user_agent) }} · {{ \Illuminate\Support\Carbon::createFromTimestamp($session->last_activity)->diffForHumans(short: true) }}</span>
                                                        </p>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>

                                        <div class="rounded-xl border border-line bg-canvas/60 p-4">
                                            <p class="mono-label">Monitoring &amp; account</p>
                                            @if($monitoring)
                                                <div class="mt-2 space-y-1 text-[11px] text-muted">
                                                    <p><span class="text-ink">Status:</span> {{ $monitoring->is_active ? 'active' : 'paused' }}</p>
                                                    <p><span class="text-ink">Cadence:</span> {{ $monitoring->cadence ?? 'weekly' }}</p>
                                                    <p><span class="text-ink">Next run:</span> {{ $monitoring->nextScanLabel() }}</p>
                                                </div>
                                                <button type="button" wire:click="toggleMonitoring({{ $user->id }})"
                                                        wire:confirm="{{ $monitoring->is_active ? 'Pause' : 'Resume' }} monitoring for {{ $user->email }}?"
                                                        class="btn-secondary mt-2 !px-3 !py-1.5 !text-xs">
                                                    {{ $monitoring->is_active ? 'Pause monitoring' : 'Resume monitoring' }}
                                                </button>
                                            @else
                                                <p class="mt-2 text-[11px] text-faint">No monitoring schedule for this identity.</p>
                                            @endif
                                            <p class="mt-2 text-[10px] text-faint">
                                                Credential monitoring: {{ config('traceless.breach.provider') ? config('traceless.breach.provider') : 'not configured' }}
                                            </p>

                                            @if($canActOn)
                                                <div class="mt-3 space-y-2 border-t border-line pt-3">
                                                    <p class="text-[10px] font-medium uppercase tracking-wider text-faint">Account state</p>
                                                    <div class="flex flex-wrap gap-1.5">
                                                        @if($isPending)
                                                            <button type="button" wire:click="markVerified({{ $user->id }})"
                                                                    wire:confirm="Mark {{ $user->email }} as verified and enable access?"
                                                                    class="btn-secondary !px-3 !py-1.5 !text-xs">Verify email</button>
                                                        @else
                                                            <button type="button" wire:click="markPending({{ $user->id }})"
                                                                    wire:confirm="Set {{ $user->email }} to pending verification? They will be signed out on the next request."
                                                                    class="btn-secondary !px-3 !py-1.5 !text-xs">Pending verification</button>
                                                        @endif
                                                        @if($user->isSuspended())
                                                            <button type="button" wire:click="activate({{ $user->id }})"
                                                                    class="btn-secondary !px-3 !py-1.5 !text-xs">Enable account</button>
                                                        @else
                                                            <button type="button" wire:click="suspend({{ $user->id }})"
                                                                    wire:confirm="Disable {{ $user->email }}? They keep their data but cannot sign in."
                                                                    class="btn-secondary !px-3 !py-1.5 !text-xs">Disable account</button>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="7" class="px-5 py-8 text-center text-sm text-muted">No accounts match this filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
            <div class="border-t border-line px-5 py-3">{{ $users->links() }}</div>
        @endif
    </div>

    <p class="text-[11px] leading-relaxed text-faint">
        Role changes require a super admin. Nobody can disable or reset their own account, and an admin
        cannot act on a super admin. Pending verification signs the account out on its next request.
        These rules are enforced in the component, not only in the interface.
    </p>
</div>
