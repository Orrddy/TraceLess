<?php

namespace App\Livewire\Removals;

use App\Enums\RemovalStatus;
use App\Models\RemovalInstruction;
use App\Models\RemovalTask;
use App\Services\RemovalWorkflowService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class RemovalShow extends Component
{
    public RemovalTask $task;

    public ?RemovalInstruction $instruction = null;

    public string $notes = '';

    public string $statusDraft = 'ready_to_remove';

    public function mount(RemovalTask $removal): void
    {
        abort_unless($removal->identityProfile->user_id === Auth::id(), 403);
        $this->task = $removal->load(['finding', 'identityProfile']);
        $this->instruction = RemovalInstruction::where('source_identifier', str($removal->source_name)->slug()->toString())->first();
        $this->notes = (string) $removal->notes;
        $this->statusDraft = $removal->status;
    }

    public function updateStatus(string $status, RemovalWorkflowService $workflow): void
    {
        abort_unless($this->task->identityProfile->user_id === Auth::id(), 403);
        $workflow->updateStatus($this->task, $status, $this->notes ?: null);
        $this->task->refresh();
        $this->statusDraft = $this->task->status;
        $this->dispatch('notify', message: 'Removal task updated.');
    }

    public function applyStatus(RemovalWorkflowService $workflow): void
    {
        $this->validate(['statusDraft' => ['required', Rule::in(RemovalStatus::values())]]);
        $this->updateStatus($this->statusDraft, $workflow);
    }

    public function saveNotes(): void
    {
        abort_unless($this->task->identityProfile->user_id === Auth::id(), 403);
        $this->validate(['notes' => 'nullable|string|max:2000']);
        $this->task->update(['notes' => strip_tags($this->notes)]);
        $this->dispatch('notify', message: 'Notes saved.');
    }

    public function render()
    {
        return view('livewire.removal-show')
            ->layout('layouts.app', ['title' => 'Removal Task // TraceLess']);
    }
}
