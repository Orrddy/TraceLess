<?php

namespace App\Events;

use App\Models\Scan;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScanProgressUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Scan $scan) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->scan->identityProfile->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'scan.progress';
    }

    public function broadcastWith(): array
    {
        return [
            'scan_id' => $this->scan->id,
            'status' => $this->scan->status,
            'progress' => $this->scan->progress_percentage,
            'stage' => $this->scan->current_stage,
            'sources_checked' => $this->scan->sources_checked,
            'sources_total' => $this->scan->sources_total,
            'findings_count' => $this->scan->findings_count,
        ];
    }
}
