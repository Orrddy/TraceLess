<?php

namespace App\Livewire\Admin;

use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Finding;
use App\Models\IdentityProfile;
use App\Models\LoginEvent;
use App\Models\MonitoringSubscription;
use App\Models\OutboundEmail;
use App\Models\QuickScanRecord;
use App\Models\RemovalTask;
use App\Models\Scan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Every number on this page is a live database query. There is no demo fallback:
 * if the count is zero it is because the table is empty.
 */
class Overview extends Component
{
    /**
     * The route carries the admin middleware, but Livewire components can be
     * instantiated directly through /livewire/update. Without this guard a
     * client could fetch the rendered page and read the whole operation.
     */
    public function mount(): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
    }

    private function ageInMinutes(mixed $value): ?int
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return (int) Carbon::parse($value)->diffInMinutes(now(), true);
        } catch (\Throwable) {
            return null;
        }
    }

    public function render()
    {
        $stats = [
            'users_total' => User::count(),
            'users_new' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'admins_total' => User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])->count(),
            'profiles_total' => IdentityProfile::count(),
            'scans_total' => Scan::count(),
            'scans_today' => Scan::where('created_at', '>=', now()->startOfDay())->count(),
            'scans_active' => Scan::whereIn('status', ['queued', 'initializing', 'checking_sources', 'normalizing_results', 'calculating_risk', 'generating_report'])->count(),
            'scans_failed' => Scan::where('status', 'failed')->count(),
            'quick_scans_total' => QuickScanRecord::count(),
            'quick_scans_today' => QuickScanRecord::where('created_at', '>=', now()->startOfDay())->count(),
            'findings_total' => Finding::count(),
            'findings_new' => Finding::where('created_at', '>=', now()->subDays(7))->count(),
            'removals_total' => RemovalTask::count(),
            'removals_done' => RemovalTask::where('status', 'removed')->count(),
            'alerts_unread' => Alert::where('is_read', false)->count(),
            'subs_active' => Subscription::whereIn('status', ['active', 'trialing'])->count(),
            'queue_pending' => DB::table('jobs')->count(),
            'queue_failed' => DB::table('failed_jobs')->count(),
            'queue_heartbeat_age' => $this->ageInMinutes(Cache::get('heartbeat:queue')),
            'schedule_heartbeat_age' => $this->ageInMinutes(Cache::get('heartbeat:schedule')),
            'email_sent_24h' => OutboundEmail::where('status', 'sent')->where('created_at', '>=', now()->subDay())->count(),
            'email_failed_24h' => OutboundEmail::where('status', 'failed')->where('created_at', '>=', now()->subDay())->count(),
            'users_pending' => User::whereNull('email_verified_at')->count(),
            'users_disabled' => User::whereNotNull('suspended_at')->count(),
            'monitors_active' => MonitoringSubscription::where('is_active', true)->count(),
        ];

        return view('livewire.admin.overview', [
            'stats' => $stats,
            'scanStatuses' => Scan::query()
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->orderByDesc('total')
                ->pluck('total', 'status'),
            'recentScans' => Scan::with('identityProfile.user')->latest()->take(8)->get(),
            'recentUsers' => User::withCount('scans')->latest()->take(6)->get(),
            'recentSignIns' => LoginEvent::with('user')->latest('logged_in_at')->take(8)->get(),
            'recentAudit' => AuditLog::with('user')->latest()->take(8)->get(),
        ])->layout('layouts.app', ['title' => 'Admin overview — TraceLess']);
    }
}
