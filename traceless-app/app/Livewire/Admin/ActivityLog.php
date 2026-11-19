<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Read-only activity log. It shows the same audit_logs rows the application has
 * always written, plus the rows written by admin actions (prefixed `admin.`).
 */
class ActivityLog extends Component
{
    use WithPagination;

    public function mount(): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
    }

    public string $search = '';

    public string $actionFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function updated($property): void
    {
        if (in_array($property, ['search', 'actionFilter', 'dateFrom', 'dateTo'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'actionFilter', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function render()
    {
        $logs = AuditLog::query()
            ->with('user')
            ->when($this->search !== '', function ($query) {
                $term = '%'.trim($this->search).'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('action', 'like', $term)
                        ->orWhere('details', 'like', $term)
                        ->orWhere('ip_address', 'like', $term)
                        ->orWhereHas('user', function ($user) use ($term) {
                            $user->where('name', 'like', $term)->orWhere('email', 'like', $term);
                        });
                });
            })
            ->when($this->actionFilter !== '', fn ($query) => $query->where('action', $this->actionFilter))
            ->when($this->dateFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $this->dateTo))
            ->latest()
            ->paginate(25);

        return view('livewire.admin.activity-log', [
            'logs' => $logs,
            'actions' => AuditLog::query()->select('action')->distinct()->orderBy('action')->limit(60)->pluck('action'),
            'todayCount' => AuditLog::where('created_at', '>=', now()->startOfDay())->count(),
            'weekCount' => AuditLog::where('created_at', '>=', now()->subDays(7))->count(),
        ])->layout('layouts.app', ['title' => 'Admin activity log // TraceLess']);
    }
}
