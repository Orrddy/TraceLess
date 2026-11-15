<?php

namespace App\Services;

use App\Models\IdentityProfile;
use App\Models\Report;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReportService
{
    public function generate(IdentityProfile $profile, string $type = 'snapshot', ?int $scanId = null): Report
    {
        $findings = $profile->findings()->with('removalTask')->get();
        $credentials = $profile->credentialExposures()->get();
        $removals = $profile->removalTasks()->get();
        $score = app(RiskScoringService::class)->calculate($profile->id);

        $summary = [
            'profile' => $profile->full_name,
            'privacy_score' => $score['privacy_score'],
            'exposure_level' => $score['exposure_level'],
            'findings' => [
                'total' => $score['total_findings'],
                'active' => $score['active_findings'],
                'removed' => $score['removed_findings'],
                'critical' => $score['critical_count'],
                'high' => $score['high_count'],
                'moderate' => $score['moderate_count'],
                'low' => $score['low_count'],
            ],
            'credential_exposures' => $credentials->count(),
            'removal_tasks' => $removals->count(),
            'monitoring_active' => $score['monitoring_active'],
            'demo_mode' => (bool) config('traceless.demo_mode'),
            'generated_at' => now()->toIso8601String(),
            'disclaimer' => 'This score is an estimate designed to help prioritize privacy actions. Removal is not guaranteed for every source.',
            'attribution' => 'Public-source discovery integrates WhatsMyName (CC BY-SA 4.0) and user-scanner (MIT).',
        ];

        $report = Report::create([
            'identity_profile_id' => $profile->id,
            'title' => ucfirst(str_replace('_', ' ', $type)).' Privacy Report — '.now()->format('Y-m-d'),
            'report_type' => $type,
            'privacy_score' => $score['privacy_score'],
            'total_findings' => $findings->count(),
            'summary' => json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'generated_at' => now(),
            'demo_mode' => (bool) config('traceless.demo_mode'),
            'download_token' => Str::random(48),
        ]);

        $report->update([
            'signature' => hash_hmac('sha256', $report->id.$report->download_token.$report->created_at, config('app.key')),
        ]);

        Log::info('report_generated', [
            'report_id' => $report->id,
            'profile_id' => $profile->id,
            'scan_id' => $scanId,
            'demo_mode' => $report->demo_mode,
        ]);

        return $report;
    }

    public function summaryArray(Report $report): array
    {
        return json_decode($report->summary ?? '{}', true) ?: [];
    }
}
