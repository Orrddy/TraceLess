<?php

namespace App\Http\Controllers;

use App\Models\Scan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ScanStatusController extends Controller
{
    /**
     * Machine-readable scan state for the live progress screen. The page polls
     * this so every device shows the same absolutely-timed countdown even after
     * a logout, another browser or a phone.
     */
    public function __invoke(Scan $scan): JsonResponse
    {
        Gate::authorize('view', $scan);

        $scan->refresh();
        $eta = $scan->estimatedCompletionAt();
        $queuePosition = 1 + Scan::whereIn('status', ['queued', 'checking_sources', 'normalizing_results', 'calculating_risk', 'generating_report'])
            ->where('id', '<', $scan->id)
            ->count();

        return response()->json([
            'id' => $scan->id,
            'reference' => $scan->reference(),
            'type' => $scan->type,
            'status' => $scan->status,
            'status_label' => $scan->statusLabel(),
            'progress' => (int) $scan->progress_percentage,
            'stage' => $scan->current_stage,
            'sources_checked' => (int) $scan->sources_checked,
            'sources_total' => (int) $scan->sources_total,
            'findings_count' => (int) $scan->findings_count,
            'is_active' => $scan->isActive(),
            'is_complete' => $scan->status === 'completed',
            'is_presented_active' => $scan->isPresentedActive(),
            'holding_results' => $scan->isHoldingResults(),
            'queue_position' => $queuePosition,
            'error_message' => $scan->error_message,
            'started_at' => $scan->started_at?->toIso8601String(),
            'completed_at' => $scan->completed_at?->toIso8601String(),
            'estimated_completion_at' => $eta?->toIso8601String(),
            'deadline_unix_ms' => $eta?->getTimestampMs(),
            'server_now_unix_ms' => now()->getTimestampMs(),
            'dashboard_url' => route('dashboard'),
        ])->header('Cache-Control', 'no-store');
    }
}
