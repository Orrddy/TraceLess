<?php

namespace App\Livewire\Removals;

use App\Models\IdentityProfile;
use App\Models\RemovalTask;
use App\Services\RemovalWorkflowService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class RemovalCenter extends Component
{
    public ?IdentityProfile $profile = null;

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->profile = IdentityProfile::where('user_id', Auth::id())->latest()->first();
    }

    public function updateTaskStatus(int $taskId, string $newStatus, RemovalWorkflowService $workflow): void
    {
        $task = RemovalTask::find($taskId);
        if ($task && $task->identity_profile_id === $this->profile?->id) {
            $workflow->updateStatus($task, $newStatus);
            $this->dispatch('notify', message: 'Removal status updated.');
        }
    }

    public function render()
    {
        $query = $this->profile
            ? RemovalTask::where('identity_profile_id', $this->profile->id)->with('finding')
            : RemovalTask::whereRaw('1 = 0');

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        $tasks = $query->latest()->get();

        $byStatus = $this->profile
            ? RemovalTask::where('identity_profile_id', $this->profile->id)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
            : collect();

        return view('livewire.removal-center', compact('tasks', 'byStatus'))
            ->layout('layouts.app', ['title' => 'Removal guidance — TraceLess']);
    }
}
