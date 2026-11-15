<?php

namespace App\Events;

use App\Models\Finding;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FindingDiscovered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Finding $finding) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->finding->identityProfile->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'finding.discovered';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->finding->id,
            'source' => $this->finding->source,
            'title' => $this->finding->title,
            'risk' => $this->finding->risk,
            'category' => $this->finding->category,
            'scan_id' => $this->finding->scan_id,
        ];
    }
}
