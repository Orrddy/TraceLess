<?php

namespace App\Livewire\Admin;

use App\Models\Scan;
use App\Services\AuditService;
use App\Services\ScanOrchestrator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Per-scan forensics: every source outcome, the produced findings, the failure
 * message and the actions an operator needs (retry, cancel).
 */
class ScanInspector extends Component
{
    public Scan $scan;

    public function mount(Scan $scan): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $this->scan = $scan->load(['identityProfile.user']);
    }

    public function retry(ScanOrchestrator $orchestrator, AuditService $audit): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $profile = $this->scan->identityProfile;

        if (! $profile) {
            $this->addError('action', 'The profile for this scan no longer exists.');

            return;
        }

        $new = $orchestrator->queueScan($profile, $this->scan->type);

        $audit->log(
            Auth::id(),
            'admin.scan_retried',
            "Scan #{$this->scan->id} retried as scan #{$new->id} ({$this->scan->type}).",
            request()->ip(),
        );

        $this->redirectRoute('admin.scans.show', $new);
    }

    public function cancel(AuditService $audit): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        if (! $this->scan->isActive()) {
            $this->addError('action', 'This scan is already finished.');

            return;
        }

        $this->scan->update([
            'status' => 'cancelled',
            'current_stage' => 'Cancelled by an administrator.',
            'completed_at' => now(),
            'cancelled_at' => now(),
        ]);

        $audit->log(Auth::id(), 'admin.scan_cancelled', "Scan #{$this->scan->id} cancelled.", request()->ip());

        $this->scan->refresh();
        $this->dispatch('notify', message: 'Scan cancelled.');
    }

    public function render()
    {
        return view('livewire.admin.scan-inspector', [
            'sources' => $this->scan->scanSources()->orderBy('id')->get(),
            'findings' => $this->scan->findings()->orderByDesc('id')->get(),
            'outcomeCounts' => $this->scan->scanSources()
                ->selectRaw('outcome, count(*) as total')
                ->groupBy('outcome')
                ->pluck('total', 'outcome'),
        ])->layout('layouts.app', ['title' => 'Scan #'.$this->scan->id.' // TraceLess']);
    }
}
