<?php

namespace App\Services\IpIntelligence;

/**
 * Weighted risk scoring for one merged intelligence record.
 *
 * Provider scores are never averaged: each signal group has its own weight,
 * overlapping anonymity flags are folded into one capped cluster, and missing
 * provider coverage adds a penalty so an unverified IP never reads as clean.
 *
 * Group weights (maximum contribution before the 100 cap):
 *  - anonymity cluster (Tor/VPN/proxy flags): up to ~65 combined, capped
 *  - abuse reputation (AbuseIPDB): up to 30, or 8/15/25 from report count
 *  - hosting / datacenter network: up to 12
 *  - provider-specific risk score (proxycheck risk, IP2Location fraud): up to 12
 *  - coverage penalty: 40 (0 providers), 22 (1), 12 (2), 5 (3), 0 (4)
 */
class IpIntelligenceScorer
{
    private const FLAG_WEIGHTS = [
        'is_tor' => [45, 'Tor exit node detected'],
        'is_open_proxy' => [20, 'Open proxy detected'],
        'is_vpn' => [18, 'VPN connection detected'],
        'is_proxy' => [16, 'Proxy connection detected'],
        'is_hosting_proxy' => [14, 'Hosting proxy detected'],
        'is_residential_proxy' => [12, 'Residential proxy detected'],
        'anonymous' => [8, 'Anonymous connection indicators present'],
    ];

    private const COVERAGE_PENALTIES = [0 => 40, 1 => 28, 2 => 15, 3 => 6, 4 => 0];

    /**
     * @param  array<string, mixed>  $data  Merged intelligence fields.
     * @param  array<string, ProviderResult>  $results
     * @return array{risk_score: int, risk_grade: string, risk_classification: string, confidence: int, reasons: array<int, string>}
     */
    public function score(array $data, array $results): array
    {
        $reasons = [];
        $anonymity = $this->anonymityContribution($data, $reasons);
        $abuse = $this->abuseContribution($data, $reasons);
        $abuseFeeds = $this->abuseFeedContribution($results, $reasons);
        $hosting = $this->hostingContribution($data, $reasons);
        $providerRisk = $this->providerRiskContribution($results, $reasons);
        $coverage = $this->coverageContribution($results, $reasons);

        $score = (int) min(100, max(0, $anonymity + $abuse + $abuseFeeds + $hosting + $providerRisk + $coverage));

        return [
            'risk_score' => $score,
            'risk_grade' => $this->grade($score),
            'risk_classification' => $this->classification($score),
            'confidence' => $this->confidence($results),
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $reasons
     */
    private function anonymityContribution(array $data, array &$reasons): int
    {
        $hits = [];

        foreach (self::FLAG_WEIGHTS as $field => [$weight, $reason]) {
            if (($data[$field] ?? null) === true) {
                $hits[$field] = $weight;
                $reasons[] = $reason;
            }
        }

        if ($hits === []) {
            return 0;
        }

        $strongest = max($hits);
        $overlap = array_sum($hits) - $strongest;

        return (int) $strongest + min(20, (int) round($overlap * 0.3));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $reasons
     */
    private function abuseContribution(array $data, array &$reasons): int
    {
        $confidence = $data['abuse_confidence'] ?? null;

        if (is_int($confidence)) {
            if ($confidence >= 25) {
                $reasons[] = "Abuse confidence {$confidence}% (AbuseIPDB)";
            }

            return (int) round(min(100, max(0, $confidence)) * 0.30);
        }

        $reports = $data['abuse_reports'] ?? null;

        if (! is_int($reports) || $reports <= 0) {
            return 0;
        }

        $reasons[] = "{$reports} abuse report(s) on record (AbuseIPDB)";

        return match (true) {
            $reports >= 100 => 25,
            $reports >= 10 => 15,
            default => 8,
        };
    }

    /**
     * ipapi.is exposes an is_abuser flag from public blocklists and honeypots.
     *
     * @param  array<string, ProviderResult>  $results
     * @param  array<int, string>  $reasons
     */
    private function abuseFeedContribution(array $results, array &$reasons): int
    {
        foreach ($results as $result) {
            if ($result->isOk() && ($result->normalized['is_abuser'] ?? null) === true) {
                $reasons[] = 'Recently seen in abuse reporting feeds';

                return 10;
            }
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $reasons
     */
    private function hostingContribution(array $data, array &$reasons): int
    {
        if (($data['datacenter'] ?? null) === true) {
            $reasons[] = 'Network belongs to a hosting or datacenter range';

            return 12;
        }

        if (($data['classification'] ?? null) === 'hosting') {
            $reasons[] = 'Provider classifies the network as hosting';

            return 8;
        }

        return 0;
    }

    /**
     * Provider-native risk scores share a 0-100 scale but not a definition, so
     * only the strongest one contributes, at 12%.
     *
     * @param  array<string, ProviderResult>  $results
     * @param  array<int, string>  $reasons
     */
    private function providerRiskContribution(array $results, array &$reasons): int
    {
        $highest = null;

        foreach ($results as $result) {
            $risk = $result->normalized['provider_risk'] ?? null;

            if ($result->isOk() && is_int($risk)) {
                $highest = max($highest ?? 0, min(100, max(0, $risk)));
            }
        }

        if ($highest === null) {
            return 0;
        }

        if ($highest >= 25) {
            $reasons[] = "Provider risk score is elevated ({$highest}/100)";
        }

        return (int) round($highest * 0.12);
    }

    /**
     * Missing data is uncertainty, not safety.
     *
     * @param  array<string, ProviderResult>  $results
     * @param  array<int, string>  $reasons
     */
    private function coverageContribution(array $results, array &$reasons): int
    {
        $ok = count(array_filter($results, fn (ProviderResult $result) => $result->isOk()));
        $total = count($results);

        $penalty = self::COVERAGE_PENALTIES[min($ok, 4)] ?? 0;

        if ($penalty > 0) {
            $reasons[] = "Limited provider coverage ({$ok}/{$total} providers responded)";
        }

        return $penalty;
    }

    /**
     * @param  array<string, ProviderResult>  $results
     */
    private function confidence(array $results): int
    {
        $weights = (array) config('traceless.ip_intelligence.providers', []);
        $sum = 0.0;

        foreach ($results as $name => $result) {
            if ($result->isOk()) {
                $sum += (float) ($weights[$name]['weight'] ?? 0.0);
            }
        }

        return (int) round(min(1.0, $sum) * 100);
    }

    private function grade(int $score): string
    {
        return match (true) {
            $score >= 85 => 'F',
            $score >= 70 => 'E',
            $score >= 50 => 'D',
            $score >= 30 => 'C',
            $score >= 15 => 'B',
            default => 'A',
        };
    }

    private function classification(int $score): string
    {
        return match (true) {
            $score >= 75 => 'critical',
            $score >= 50 => 'high',
            $score >= 25 => 'moderate',
            default => 'low',
        };
    }
}
