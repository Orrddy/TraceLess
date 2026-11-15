<?php

namespace App\Notifications;

use App\Models\RemovalTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RemovalStatusNotification extends Notification
{
    use Queueable;

    public function __construct(public RemovalTask $task) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'removal_status',
            'removal_id' => $this->task->id,
            'title' => 'Removal status updated',
            'message' => "{$this->task->source_name}: ".str($this->task->status)->replace('_', ' ')->title()->toString().'.',
            'url' => route('removals.show', $this->task),
            'severity' => $this->task->status === 'removed' ? 'low' : 'moderate',
        ];
    }
}
