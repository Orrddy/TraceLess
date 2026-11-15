<?php

namespace App\Services;

use App\Enums\RemovalStatus;
use App\Events\RemovalStatusUpdated;
use App\Models\RemovalTask;
use App\Notifications\RemovalStatusNotification;

class RemovalWorkflowService
{
    protected const FINDING_STATUS_MAP = [
        'not_started' => 'needs_user_action',
        'ready_to_remove' => 'ready_to_remove',
        'in_progress' => 'in_progress',
        'waiting_for_source' => 'waiting_for_source',
        'user_action_required' => 'user_action_required',
        'removed' => 'removed',
        'could_not_verify' => 'could_not_verify',
        'reappeared' => 'reappeared',
    ];

    public function updateStatus(RemovalTask $task, string $newStatus, ?string $notes = null): RemovalTask
    {
        $validStatuses = RemovalStatus::values();

        if (! in_array($newStatus, $validStatuses, true)) {
            throw new \InvalidArgumentException("Invalid removal status: {$newStatus}");
        }

        $task->status = $newStatus;
        if ($notes) {
            $task->notes = $notes;
        }

        if ($newStatus === 'in_progress' && ! $task->requested_at) {
            $task->requested_at = now();
        }

        if ($newStatus === 'removed') {
            $task->completed_at = now();
        } elseif ($task->completed_at && $newStatus !== 'removed') {
            $task->completed_at = null;
        }

        $task->save();

        $findingStatus = self::FINDING_STATUS_MAP[$newStatus] ?? 'needs_user_action';
        $task->finding?->update([
            'status' => $findingStatus,
            'last_checked_at' => now(),
        ]);

        app(RiskScoringService::class)->recalculateAndPersist($task->identity_profile_id);

        event(new RemovalStatusUpdated($task->fresh()));
        $task->identityProfile->user->notify(new RemovalStatusNotification($task->fresh()));

        app(AuditService::class)->log(
            $task->identityProfile->user_id,
            'removal_status_updated',
            "Removal task #{$task->id} moved to {$newStatus}.",
            request()?->ip()
        );

        return $task;
    }

    public function markReappeared(RemovalTask $task): RemovalTask
    {
        return $this->updateStatus($task, 'reappeared', 'Source republished the record after removal.');
    }

    public function progressFor(int $identityProfileId): array
    {
        $tasks = RemovalTask::where('identity_profile_id', $identityProfileId)->get();
        $total = max(1, $tasks->count());

        return [
            'total' => $tasks->count(),
            'removed' => $tasks->where('status', 'removed')->count(),
            'in_progress' => $tasks->where('status', 'in_progress')->count(),
            'ready' => $tasks->where('status', 'ready_to_remove')->count(),
            'waiting' => $tasks->where('status', 'waiting_for_source')->count(),
            'percent' => (int) round(($tasks->where('status', 'removed')->count() / $total) * 100),
        ];
    }
}
