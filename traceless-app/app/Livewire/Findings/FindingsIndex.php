<?php

namespace App\Livewire\Findings;

use App\Models\Finding;
use App\Models\IdentityProfile;
use App\Services\RiskScoringService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class FindingsIndex extends Component
{
    use WithPagination;

    public ?IdentityProfile $profile = null;

    public string $riskFilter = '';

    public string $categoryFilter = '';

    public string $statusFilter = '';

    public string $search = '';

    public string $sort = 'newest';

    protected $queryString = [
        'riskFilter' => ['except' => ''],
        'categoryFilter' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'search' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->profile = IdentityProfile::where('user_id', Auth::id())->latest()->first();
    }

    public function updated($property): void
    {
        if (in_array($property, ['riskFilter', 'categoryFilter', 'statusFilter', 'search', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['riskFilter', 'categoryFilter', 'statusFilter', 'search', 'sort']);
        $this->resetPage();
    }

    public function markNotMine(int $findingId, RiskScoringService $scorer): void
    {
        $finding = Finding::findOrFail($findingId);
        abort_unless($finding->identityProfile->user_id === Auth::id(), 403);
        $finding->update(['status' => 'could_not_verify']);
        $scorer->recalculateAndPersist($finding->identity_profile_id);
    }

    public function render()
    {
        $baseQuery = Finding::query();
        if ($this->profile) {
            $baseQuery->where('identity_profile_id', $this->profile->id);
        } else {
            $baseQuery->whereRaw('1 = 0');
        }

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'high' => (clone $baseQuery)->whereIn('risk', ['high', 'critical'])->count(),
            'removed' => (clone $baseQuery)->where('status', 'removed')->count(),
            'brokers' => (clone $baseQuery)->where('category', 'data_broker')->count(),
        ];

        $query = clone $baseQuery;

        if ($this->riskFilter) {
            $query->where('risk', $this->riskFilter);
        }
        if ($this->categoryFilter) {
            $query->where('category', $this->categoryFilter);
        }
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('source', 'like', '%'.$this->search.'%')
                    ->orWhere('identifier_value', 'like', '%'.$this->search.'%')
                    ->orWhere('title', 'like', '%'.$this->search.'%');
            });
        }

        $findings = match ($this->sort) {
            'risk' => $query->orderByRaw("CASE risk WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'moderate' THEN 2 ELSE 3 END")->latest(),
            'confidence' => $query->orderByDesc('confidence'),
            'oldest' => $query->oldest(),
            default => $query->latest(),
        };

        return view('livewire.findings-index', [
            'findings' => $findings->paginate(15),
            'stats' => $stats,
        ])->layout('layouts.app', ['title' => 'Findings // TraceLess']);
    }
}
