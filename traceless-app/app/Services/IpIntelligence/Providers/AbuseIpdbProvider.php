<?php

namespace App\Services\IpIntelligence\Providers;

use App\Services\IpIntelligence\IpProvider;
use App\Services\IpIntelligence\ParsesProviderPayload;

/**
 * AbuseIPDB: community abuse reports, confidence and categories.
 *
 * https://docs.abuseipdb.com/#check-endpoint
 */
class AbuseIpdbProvider implements IpProvider
{
    use ParsesProviderPayload;

    /**
     * Category ids from the AbuseIPDB documentation. Unknown ids stay numeric so
     * the response never invents a label.
     */
    private const CATEGORIES = [
        1 => 'DNS Compromise',
        2 => 'DNS Poisoning',
        3 => 'Fraud Orders',
        4 => 'DDoS Attack',
        5 => 'FTP Brute-Force',
        6 => 'Ping of Death',
        7 => 'Phishing',
        8 => 'Fraud VoIP',
        9 => 'Open Proxy',
        10 => 'Web Spam',
        11 => 'Email Spam',
        12 => 'Blog Spam',
        13 => 'VPN IP',
        14 => 'Port Scan',
        15 => 'Hacking',
        16 => 'SQL Injection',
        17 => 'Spoofing',
        18 => 'Brute Force',
        19 => 'Bad Web Bot',
        20 => 'Exploited Host',
        21 => 'Web App Attack',
        22 => 'SSH',
        23 => 'IoT Targeted',
    ];

    public function name(): string
    {
        return 'abuseipdb';
    }

    public function label(): string
    {
        return 'AbuseIPDB';
    }

    public function isConfigured(): bool
    {
        return $this->key() !== '';
    }

    public function url(string $ip): string
    {
        $endpoint = (string) config('traceless.ip_intelligence.providers.abuseipdb.endpoint');

        return $endpoint.'?ipAddress='.urlencode($ip).'&maxAgeInDays=90';
    }

    public function headers(): array
    {
        return [
            'Key' => $this->key(),
            'Accept' => 'application/json',
        ];
    }

    public function normalize(array $payload, string $ip): array
    {
        $report = is_array($payload['data'] ?? null) ? $payload['data'] : null;

        if ($report === null) {
            throw new \InvalidArgumentException('AbuseIPDB payload contains no data.');
        }

        $categories = [];

        foreach ((array) ($report['reports'] ?? []) as $entry) {
            foreach ((array) ($entry['categories'] ?? []) as $id) {
                $categories[$this->intValue($id) ?? 0] = self::CATEGORIES[$this->intValue($id) ?? 0]
                    ?? 'category_'.($this->intValue($id) ?? 0);
            }
        }

        $categoryIds = array_keys($categories);

        $signals = $this->emptySignals();
        $signals['country_code'] = $this->text($report['countryCode'] ?? null);
        $signals['isp'] = $this->text($report['isp'] ?? null);
        $signals['connection_type'] = $this->text($report['usageType'] ?? null);
        $signals['classification_hint'] = $this->classificationHint($signals['connection_type']);
        $signals['is_vpn'] = in_array(13, $categoryIds, true) ? true : null;
        $signals['is_open_proxy'] = in_array(9, $categoryIds, true) ? true : null;
        $signals['is_proxy'] = $signals['is_open_proxy'];
        $signals['abuse_confidence'] = $this->intValue($report['abuseConfidenceScore'] ?? null);
        $signals['abuse_reports'] = $this->intValue($report['totalReports'] ?? null);
        $signals['abuse_last_reported'] = $this->text($report['lastReportedAt'] ?? null);
        $signals['abuse_categories'] = $categories === [] ? [] : array_values($categories);
        $signals['anonymous'] = $this->anonymousFrom([
            'vpn' => $signals['is_vpn'],
            'proxy' => $signals['is_proxy'],
        ]);

        return $signals;
    }

    private function key(): string
    {
        return trim((string) config('traceless.ip_intelligence.providers.abuseipdb.key'));
    }
}
