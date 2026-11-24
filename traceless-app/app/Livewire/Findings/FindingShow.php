<?php

namespace App\Livewire\Findings;

use App\Models\Finding;
use App\Models\RemovalInstruction;
use App\Models\RemovalTask;
use App\Services\AuditService;
use App\Services\RemovalWorkflowService;
use App\Services\RiskScoringService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class FindingShow extends Component
{
    public Finding $finding;

    public ?RemovalInstruction $instruction = null;

    public string $notes = '';

    public function mount(Finding $finding): void
    {
        Gate::authorize('view', $finding);
        $this->finding = $finding->load(['removalTask', 'scan', 'identityProfile']);
        $this->instruction = RemovalInstruction::where('source_identifier', str($finding->source)->slug()->toString())->first();
        $this->notes = (string) $finding->removalTask?->notes;
    }

    public function startRemoval(RemovalWorkflowService $workflow): void
    {
        Gate::authorize('update', $this->finding);

        $task = $this->finding->removalTask;
        if (! $task) {
            $task = RemovalTask::create([
                'identity_profile_id' => $this->finding->identity_profile_id,
                'finding_id' => $this->finding->id,
                'source_name' => $this->finding->source,
                'status' => 'ready_to_remove',
                'workflow_type' => $this->finding->removal_method === 'automated_request' ? 'automated' : 'manual',
                'estimated_turnaround' => $this->instruction?->typical_turnaround ?? '1–7 days',
                'opt_out_url' => $this->instruction?->direct_opt_out_link ?? $this->finding->url,
            ]);
        }

        $workflow->updateStatus($task, 'in_progress', $this->notes ?: null);
        $this->finding->refresh();
        $this->dispatch('notify', message: 'Removal workflow started.');
    }

    public function markRemoved(RemovalWorkflowService $workflow): void
    {
        Gate::authorize('update', $this->finding);
        if ($task = $this->finding->removalTask) {
            $workflow->updateStatus($task, 'removed', $this->notes ?: 'User confirmed removal.');
            $this->finding->refresh();
        }
    }

    public function markNotMine(RiskScoringService $scorer): void
    {
        Gate::authorize('update', $this->finding);
        $this->finding->update(['status' => 'could_not_verify', 'last_checked_at' => now()]);
        $scorer->recalculateAndPersist($this->finding->identity_profile_id);
        app(AuditService::class)->log(Auth::id(), 'finding_marked_not_mine', "Finding #{$this->finding->id} marked as not matching.", request()->ip());
        $this->redirectRoute('findings');
    }

    public function saveNotes(): void
    {
        Gate::authorize('update', $this->finding);
        $this->validate(['notes' => 'nullable|string|max:2000']);
        $this->finding->removalTask?->update(['notes' => strip_tags($this->notes)]);
        $this->dispatch('notify', message: 'Removal notes saved.');
    }

    public function render()
    {
        return view('livewire.finding-show')
            ->layout('layouts.app', ['title' => 'Finding details — TraceLess']);
    }
}
