<?php

namespace App\Services;

use App\Enums\RiskLevel;
use App\Models\CredentialExposure;
use App\Models\Finding;
use App\Models\IdentityProfile;
use App\Models\MonitoringSubscription;

class RiskScoringService
{
    /**
     * Calculate a 0–100 privacy posture estimate.
     *
     * The score is intentionally conservative and explainable:
     *  - open findings deduct by severity, weighted by confidence
     *  - unresolved credential exposures deduct independently
     *  - verified removals and active monitoring recover a small amount
     *
     * It is an estimate designed to prioritise actions, not a security
     * guarantee.
     */
    public function calculate(int $identityProfileId): array
    {
        $findings = Finding::where('identity_profile_id', $identityProfileId)->get();
        $creds = CredentialExposure::where('identity_profile_id', $identityProfileId)
            ->where('is_resolved', false)
            ->get();
        $subscription = MonitoringSubscription::where('identity_profile_id', $identityProfileId)->first();

        $score = 100.0;
        $counts = ['critical' => 0, 'high' => 0, 'moderate' => 0, 'low' => 0];
        $activeFindings = 0;
        $removedFindings = 0;
        $sources = [];

        foreach ($findings as $finding) {
            if (in_array($finding->status, ['removed', 'could_not_verify'], true)) {
                $removedFindings++;

                continue;
            }

            $activeFindings++;
            $sources[$finding->source] = true;
            $risk = RiskLevel::tryFrom($finding->risk) ?? RiskLevel::Low;
            $counts[$risk->value]++;

            $confidenceWeight = 0.55 + (min(1, max(0, $finding->confidence)) * 0.45);
            $score -= $risk->penalty() * $confidenceWeight;
        }

        // Cap the finding penalty so one noisy source cannot zero the score.
        $findingPenalty = 100 - $score;
        $score = 100 - min(62, $findingPenalty);

        $score -= min(24, $creds->count() * 8);

        // Recovery: verified removals and active monitoring are positive signal.
        $total = max(1, $findings->count());
        $score += ($removedFindings / $total) * 8;
        if ($subscription?->is_active) {
            $score += 4;
        }

        $score = (int) round(max(config('traceless.scan.minimum_score', 5), min(100, $score)));

        $level = match (true) {
            $score >= 85 => 'Optimal',
            $score >= 65 => 'Moderate',
            $score >= 40 => 'Elevated',
            default => 'Critical',
        };

        $exposureRatio = (int) round(($activeFindings / $total) * 100);
        $removalProgress = $findings->count() > 0
            ? (int) round(($removedFindings / $findings->count()) * 100)
            : 0;

        return [
            'privacy_score' => $score,
            'exposure_level' => $level,
            'critical_count' => $counts['critical'],
            'high_count' => $counts['high'],
            'moderate_count' => $counts['moderate'],
            'low_count' => $counts['low'],
            'credential_leaks' => $creds->count(),
            'total_findings' => $findings->count(),
            'active_findings' => $activeFindings,
            'removed_findings' => $removedFindings,
            'source_count' => count($sources),
            'exposure_ratio' => $exposureRatio,
            'removal_progress' => $removalProgress,
            'monitoring_active' => (bool) $subscription?->is_active,
            'disclaimer' => 'This score is an estimate designed to help prioritize privacy actions based on public data and removal progress.',
        ];
    }

    /**
     * Persist the latest score on the profile.
     */
    public function recalculateAndPersist(int $identityProfileId): array
    {
        $result = $this->calculate($identityProfileId);

        IdentityProfile::whereKey($identityProfileId)->update([
            'privacy_score' => $result['privacy_score'],
        ]);

        return $result;
    }
}
