<?php

namespace App\Livewire\Scans;

use App\Models\IdentityProfile;
use App\Models\Scan;
use App\Services\ScanOrchestrator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class ScansIndex extends Component
{
    use WithPagination;

    public string $status = '';

    protected $queryString = ['status' => ['except' => '']];

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function cancel(int $scanId, ScanOrchestrator $orchestrator): void
    {
        $scan = Scan::findOrFail($scanId);
        Gate::authorize('cancel', $scan);
        if ($scan->isActive()) {
            $orchestrator->cancel($scan);
            $this->dispatch('notify', message: 'Scan cancelled.');
        }
    }

    public function render()
    {
        $profileIds = IdentityProfile::where('user_id', Auth::id())->pluck('id');

        $query = Scan::whereIn('identity_profile_id', $profileIds)->with(['identityProfile'])->latest();
        if ($this->status) {
            $query->where('status', $this->status);
        }

        $stats = [
            'total' => Scan::whereIn('identity_profile_id', $profileIds)->count(),
            'active' => Scan::whereIn('identity_profile_id', $profileIds)->whereNotIn('status', ['completed', 'failed', 'cancelled'])->count(),
            'completed' => Scan::whereIn('identity_profile_id', $profileIds)->where('status', 'completed')->count(),
        ];

        return view('livewire.scans-index', [
            'scans' => $query->paginate(12),
            'stats' => $stats,
        ])->layout('layouts.app', ['title' => 'Scans // TraceLess']);
    }
}
