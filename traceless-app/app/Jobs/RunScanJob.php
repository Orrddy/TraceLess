<?php

namespace App\Jobs;

use App\Models\Scan;
use App\Services\ScanOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunScanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    // Live scans run the worker once per monitored username; allow for several.
    public int $timeout = 300;

    public function __construct(public Scan $scan) {}

    public function handle(ScanOrchestrator $orchestrator): void
    {
        $orchestrator->executeScan($this->scan);
    }
}
