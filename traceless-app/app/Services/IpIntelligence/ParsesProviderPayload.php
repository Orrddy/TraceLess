<?php

namespace App\Services\IpIntelligence;

/**
 * Defensive readers for third-party payloads.
 *
 * Provider JSON is untrusted input: it may change shape, omit fields or return
 * unexpected types. Every accessor below returns null instead of guessing.
 */
trait ParsesProviderPayload
{
    protected function text(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    protected function boolValue(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }

    /**
     * Booleans that some providers encode as "true"/"false"/"yes"/"no" or 0/1.
     */
    protected function flagValue(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                'true', 'yes', '1' => true,
                'false', 'no', '0' => false,
                default => null,
            };
        }

        return null;
    }

    /**
     * ipapi.is documents its VPN flag as truthy rather than strictly boolean.
     */
    protected function truthyFlag(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return ! in_array(strtolower(trim($value)), ['', '0', 'false', 'no', 'null'], true);
        }

        return (bool) $value;
    }

    protected function intValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    protected function floatValue(mixed $value): ?float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    protected function firstText(array $values): ?string
    {
        foreach ($values as $value) {
            if ($text = $this->text($value)) {
                return $text;
            }
        }

        return null;
    }

    /**
     * Accepts "AS64500", "64500" or 64500.
     */
    protected function asnNumber(mixed $value): int|string|null
    {
        $text = $this->text($value);

        if ($text === null) {
            return null;
        }

        $withoutPrefix = preg_replace('/^AS\s*/i', '', $text) ?? $text;

        return ctype_digit($withoutPrefix) ? (int) $withoutPrefix : $text;
    }

    /**
     * Turn a provider "usage type" string into the shared classification verb.
     */
    protected function classificationHint(?string $usageType): ?string
    {
        if ($usageType === null) {
            return null;
        }

        $value = strtolower($usageType);

        return match (true) {
            str_contains($value, 'hosting'),
            str_contains($value, 'data center'),
            str_contains($value, 'datacenter'),
            str_contains($value, 'transit'),
            str_contains($value, 'cloud') => 'hosting',
            str_contains($value, 'mobile'),
            str_contains($value, 'cellular') => 'mobile',
            str_contains($value, 'commercial'),
            str_contains($value, 'business'),
            str_contains($value, 'company'),
            str_contains($value, 'enterprise') => 'business',
            str_contains($value, 'residential'),
            str_contains($value, 'fixed line'),
            str_contains($value, 'isp'),
            str_contains($value, 'internet service') => 'residential',
            default => null,
        };
    }

    /**
     * Recursively drop credential-shaped keys before a payload is persisted.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function withoutSecrets(array $payload): array
    {
        $blocked = ['key', 'api_key', 'apikey', 'api', 'token', 'secret', 'password'];

        $clean = [];

        foreach ($payload as $name => $value) {
            if (in_array(strtolower((string) $name), $blocked, true)) {
                continue;
            }

            $clean[$name] = is_array($value) ? $this->withoutSecrets($value) : $value;
        }

        return $clean;
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptySignals(): array
    {
        return array_fill_keys([
            'country', 'country_code', 'region', 'city', 'postal_code',
            'latitude', 'longitude', 'timezone',
            'isp', 'organisation', 'asn', 'asn_organisation', 'network_range',
            'connection_type', 'classification_hint', 'datacenter', 'is_mobile',
            'is_vpn', 'is_proxy', 'is_tor', 'is_residential_proxy',
            'is_open_proxy', 'is_hosting_proxy', 'anonymous', 'is_abuser',
            'abuse_confidence', 'abuse_reports', 'abuse_last_reported', 'abuse_categories',
            'provider_risk',
        ], null);
    }

    /**
     * OR the anonymity flags a provider gave us; null when it reported none.
     *
     * @param  array<string, bool|null>  $flags
     */
    protected function anonymousFrom(array $flags): ?bool
    {
        $reported = array_filter($flags, fn ($value) => $value !== null);

        if ($reported === []) {
            return null;
        }

        return in_array(true, $reported, true);
    }
}
