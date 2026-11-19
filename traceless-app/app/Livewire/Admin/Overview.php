<?php

namespace App\Livewire\Admin;

use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Finding;
use App\Models\IdentityProfile;
use App\Models\RemovalTask;
use App\Models\Scan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
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

    public function render()
    {
        $stats = [
            'users_total' => User::count(),
            'users_new' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'admins_total' => User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])->count(),
            'profiles_total' => IdentityProfile::count(),
            'scans_total' => Scan::count(),
            'scans_today' => Scan::where('created_at', '>=', now()->startOfDay())->count(),
            'scans_active' => Scan::whereIn('status', ['queued', 'checking_sources', 'normalizing_results', 'calculating_risk'])->count(),
            'scans_failed' => Scan::where('status', 'failed')->count(),
            'findings_total' => Finding::count(),
            'findings_new' => Finding::where('created_at', '>=', now()->subDays(7))->count(),
            'removals_total' => RemovalTask::count(),
            'removals_done' => RemovalTask::where('status', 'removed')->count(),
            'alerts_unread' => Alert::where('is_read', false)->count(),
            'subs_active' => Subscription::whereIn('status', ['active', 'trialing'])->count(),
            'queue_pending' => DB::table('jobs')->count(),
            'queue_failed' => DB::table('failed_jobs')->count(),
        ];

        return view('livewire.admin.overview', [
            'stats' => $stats,
            'scanStatuses' => Scan::query()
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->orderByDesc('total')
                ->pluck('total', 'status'),
            'recentScans' => Scan::with('identityProfile.user')->latest()->take(8)->get(),
            'recentUsers' => User::latest()->take(6)->get(),
            'recentAudit' => AuditLog::with('user')->latest()->take(8)->get(),
        ])->layout('layouts.app', ['title' => 'Admin overview // TraceLess']);
    }
}
