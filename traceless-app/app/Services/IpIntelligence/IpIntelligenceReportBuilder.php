<?php

namespace App\Services\IpIntelligence;

use Illuminate\Support\Carbon;

/**
 * Merge four provider reports into the public endpoint contract.
 *
 * Field precedence favours the provider that owns that category of data. A
 * missing value stays null: nothing here invents a country, ISP or flag.
 */
class IpIntelligenceReportBuilder
{
    private const GEO_ORDER = ['ipapi_is', 'ip2location', 'proxycheck', 'abuseipdb'];

    private const NETWORK_ORDER = ['ipapi_is', 'ip2location', 'proxycheck', 'abuseipdb'];

    private const SCALAR_FIELDS = [
        'country', 'country_code', 'region', 'city', 'postal_code',
        'latitude', 'longitude', 'timezone',
        'isp', 'organisation', 'asn', 'asn_organisation', 'network_range',
        'connection_type',
        'abuse_confidence', 'abuse_reports', 'abuse_last_reported',
    ];

    private const FLAG_FIELDS = [
        'datacenter', 'is_vpn', 'is_proxy', 'is_tor', 'is_residential_proxy',
        'is_open_proxy', 'is_hosting_proxy', 'anonymous',
    ];

    public function __construct(private IpIntelligenceScorer $scorer) {}

    /**
     * @param  array<string, ProviderResult>  $results
     * @return array<string, mixed>
     */
    public function build(string $ip, int $ipVersion, array $results, Carbon $analyzedAt, Carbon $expiresAt): array
    {
        $normalized = [];

        foreach (self::GEO_ORDER as $provider) {
            if (isset($results[$provider]) && $results[$provider]->isOk() && is_array($results[$provider]->normalized)) {
                $normalized[$provider] = $results[$provider]->normalized;
            }
        }

        $data = [
            'ip' => $ip,
            'ip_version' => $ipVersion,
        ];

        foreach (self::SCALAR_FIELDS as $field) {
            $data[$field] = $this->pick($field, $normalized);
        }

        foreach (self::FLAG_FIELDS as $field) {
            $data[$field] = $this->mergeFlag($field, $normalized);
        }

        $data['abuse_categories'] = $this->mergeCategories($normalized);
        // None of the four providers returns a DNSBL feed, so the list stays
        // empty rather than pretending the IP was checked against one.
        $data['blacklist'] = [];
        $data['classification'] = $this->classification($data, $normalized);

        $data['provider_status'] = [];

        foreach ($results as $name => $result) {
            $data['provider_status'][$name] = $result->status;
        }

        $data['sources'] = array_values(array_map(
            fn (ProviderResult $result) => $result->label,
            array_filter($results, fn (ProviderResult $result) => $result->isOk()),
        ));

        $scored = $this->scorer->score($data, $results);
        $data['confidence'] = $scored['confidence'];
        $data['risk_score'] = $scored['risk_score'];
        $data['risk_grade'] = $scored['risk_grade'];
        $data['risk_classification'] = $scored['risk_classification'];
        $data['reasons'] = $scored['reasons'];
        $data['analyzed_at'] = $analyzedAt->toIso8601String();
        $data['expires_at'] = $expiresAt->toIso8601String();

        return $data;
    }

    /**
     * @param  array<string, array<string, mixed>>  $normalized
     */
    private function pick(string $field, array $normalized): mixed
    {
        $order = in_array($field, ['isp', 'organisation', 'asn', 'asn_organisation', 'network_range', 'connection_type'], true)
            ? self::NETWORK_ORDER
            : self::GEO_ORDER;

        foreach ($order as $provider) {
            $value = $normalized[$provider][$field] ?? null;

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * A flag is true when any provider reported true; false only when a provider
     * explicitly reported false and none contradicted it.
     *
     * @param  array<string, array<string, mixed>>  $normalized
     */
    private function mergeFlag(string $field, array $normalized): ?bool
    {
        $reported = false;
        $value = false;

        foreach ($normalized as $signals) {
            $candidate = $signals[$field] ?? null;

            if ($candidate === null) {
                continue;
            }

            $reported = true;
            $value = $value || $candidate;
        }

        return $reported ? $value : null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $normalized
     * @return array<int, string>
     */
    private function mergeCategories(array $normalized): array
    {
        $categories = [];

        foreach ($normalized as $signals) {
            foreach ((array) ($signals['abuse_categories'] ?? []) as $category) {
                if (is_string($category) && $category !== '') {
                    $categories[$category] = true;
                }
            }
        }

        return array_keys($categories);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<string, mixed>>  $normalized
     */
    private function classification(array $data, array $normalized): string
    {
        if (($data['datacenter'] ?? null) === true) {
            return 'hosting';
        }

        foreach (self::GEO_ORDER as $provider) {
            if (($normalized[$provider]['is_mobile'] ?? null) === true) {
                return 'mobile';
            }
        }

        foreach (self::GEO_ORDER as $provider) {
            $hint = $normalized[$provider]['classification_hint'] ?? null;

            if (is_string($hint) && in_array($hint, ['residential', 'hosting', 'mobile', 'business'], true)) {
                return $hint;
            }
        }

        return 'unknown';
    }
}
