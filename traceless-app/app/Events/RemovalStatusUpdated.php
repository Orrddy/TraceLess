<?php

namespace App\Events;

use App\Models\RemovalTask;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RemovalStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public RemovalTask $task) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->task->identityProfile->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'removal.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'task_id' => $this->task->id,
            'status' => $this->task->status,
            'source' => $this->task->source_name,
        ];
    }
}
