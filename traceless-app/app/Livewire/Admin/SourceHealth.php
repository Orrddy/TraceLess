<?php

namespace App\Livewire\Admin;

use App\Models\ScanSource;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Source health: which public sources actually answer.
 *
 * A source that times out or blocks TraceLess is recorded as "unknown" — not
 * as "not found". This page makes that difference visible so an operator can
 * tell a clean scan from a silently degraded one.
 */
class SourceHealth extends Component
{
    public int $days = 7;

    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
    }

    public function setDays(int $days): void
    {
        if (in_array($days, [7, 30, 90], true)) {
            $this->days = $days;
        }
    }

    public function render()
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $since = now()->subDays($this->days);

        $rows = ScanSource::query()
            ->where('created_at', '>=', $since)
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.trim($this->search).'%'))
            ->select('name')
            ->selectRaw('max(category) as category')
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when outcome = 'found' then 1 else 0 end) as found_count")
            ->selectRaw("sum(case when outcome = 'not_found' then 1 else 0 end) as not_found_count")
            ->selectRaw("sum(case when outcome = 'unknown' then 1 else 0 end) as unknown_count")
            ->selectRaw("sum(case when outcome = 'skipped' then 1 else 0 end) as skipped_count")
            ->selectRaw('sum(case when http_status in (401, 403, 429, 451, 999) then 1 else 0 end) as blocked_count')
            ->selectRaw('max(created_at) as last_seen')
            ->groupBy('name')
            ->orderByDesc('total')
            ->orderBy('name')
            ->limit(100)
            ->get();

        $totals = ScanSource::query()
            ->where('created_at', '>=', $since)
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.trim($this->search).'%'))
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when outcome = 'unknown' then 1 else 0 end) as unknown_count")
            ->selectRaw('sum(case when http_status in (401, 403, 429, 451, 999) then 1 else 0 end) as blocked_count')
            ->first();

        $total = (int) ($totals->total ?? 0);
        $unknown = (int) ($totals->unknown_count ?? 0);
        $blocked = (int) ($totals->blocked_count ?? 0);

        return view('livewire.admin.source-health', [
            'rows' => $rows,
            'sourceCount' => ScanSource::query()->where('created_at', '>=', $since)->distinct('name')->count('name'),
            'totalChecks' => $total,
            'unknownRate' => $total > 0 ? round(($unknown / $total) * 100, 1) : 0.0,
            'blockedRate' => $total > 0 ? round(($blocked / $total) * 100, 1) : 0.0,
            'healthyRate' => $total > 0 ? round((($total - $unknown) / $total) * 100, 1) : 100.0,
        ])->layout('layouts.app', ['title' => 'Admin source health — TraceLess']);
    }
}
