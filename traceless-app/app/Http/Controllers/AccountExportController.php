<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\Request;

class AccountExportController extends Controller
{
    public function __invoke(Request $request, AuditService $audit)
    {
        $user = $request->user();
        $profiles = $user->identityProfiles()->with([
            'publicIdentifiers',
            'findings.removalTask',
            'credentialExposures',
            'removalTasks',
            'scans',
            'monitoringSubscription',
            'consentRecords',
        ])->get();

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'profiles' => $profiles->map(fn ($profile) => [
                'full_name' => $profile->full_name,
                'previous_names' => $profile->previous_names,
                'aliases' => $profile->aliases,
                'primary_email' => $profile->primary_email,
                'phone_number' => $profile->phone_number,
                'region' => $profile->region,
                'privacy_score' => $profile->privacy_score,
                'identifiers' => $profile->publicIdentifiers->map->only(['type', 'value', 'is_monitored']),
                'findings' => $profile->findings->map->only([
                    'source', 'category', 'identifier_value', 'url', 'confidence', 'risk', 'status', 'discovered_at',
                ]),
                'credential_exposures' => $profile->credentialExposures->map->only([
                    'email', 'service_name', 'breach_date', 'exposed_data_classes', 'is_resolved',
                ]),
                'removal_tasks' => $profile->removalTasks->map->only(['source_name', 'status', 'workflow_type', 'completed_at', 'notes']),
                'scans' => $profile->scans->map->only(['type', 'status', 'findings_count', 'started_at', 'completed_at']),
                'monitoring' => $profile->monitoringSubscription?->only(['is_active', 'cadence', 'next_scan_at']),
                'consent_records' => $profile->consentRecords->map->only(['consent_text', 'agreed_at']),
            ]),
            'audit_logs' => $user->auditLogs()->latest()->get()->map->only(['action', 'details', 'ip_address', 'created_at']),
            'retention' => 'Scan telemetry is retained for '.config('traceless.retention.scan_telemetry_days').' days by default.',
        ];

        $audit->log($user->id, 'data_exported', 'User downloaded a personal data export.', $request->ip());

        return response()->json($payload, 200, [
            'Content-Disposition' => 'attachment; filename="traceless-export-'.now()->format('Y-m-d').'.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
