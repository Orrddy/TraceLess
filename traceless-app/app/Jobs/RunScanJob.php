<?php

namespace App\Jobs;

use App\Models\Scan;
use App\Services\ScanOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    /**
     * A worker that is killed outright, or a job that exceeds its timeout, never
     * reaches the orchestrator's own catch block. Without this, the scan stays in
     * a non-terminal state and blocks every future scan for that identity.
     */
    public function failed(?Throwable $exception): void
    {
        $scan = $this->scan->fresh();

        if (! $scan || ! $scan->isActive()) {
            return;
        }

        $scan->update([
            'status' => 'failed',
            'current_stage' => 'Scan stopped unexpectedly. You can start a new one.',
            'error_message' => 'The scan worker stopped before finishing. No partial results were published. Please retry.',
            'completed_at' => now(),
        ]);

        Log::warning('scan_job_failed', [
            'scan_id' => $scan->id,
            'exception' => $exception ? get_class($exception) : 'unknown',
            'message_hash' => $exception ? hash('sha256', $exception->getMessage()) : null,
        ]);
    }
}
