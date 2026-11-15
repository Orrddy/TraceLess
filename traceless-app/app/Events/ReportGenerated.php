<?php

namespace App\Events;

use App\Models\Report;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportGenerated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Report $report) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->report->identityProfile->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'report.generated';
    }

    public function broadcastWith(): array
    {
        return [
            'report_id' => $this->report->id,
            'title' => $this->report->title,
            'download_url' => $this->report->downloadUrl(),
        ];
    }
}
