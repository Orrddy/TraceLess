<?php

namespace App\Services\IpIntelligence\Providers;

use App\Services\IpIntelligence\IpProvider;
use App\Services\IpIntelligence\ParsesProviderPayload;

/**
 * IP2Location.io: secondary geolocation, ISP/ASN and proxy flags.
 *
 * https://www.ip2location.io/ip2location-documentation
 */
class Ip2locationProvider implements IpProvider
{
    use ParsesProviderPayload;

    public function name(): string
    {
        return 'ip2location';
    }

    public function label(): string
    {
        return 'IP2Location.io';
    }

    public function isConfigured(): bool
    {
        return $this->key() !== '';
    }

    public function url(string $ip): string
    {
        $base = rtrim((string) config('traceless.ip_intelligence.providers.ip2location.endpoint'), '/');

        return $base.'/?key='.urlencode($this->key()).'&ip='.urlencode($ip).'&format=json';
    }

    public function headers(): array
    {
        return [];
    }

    public function normalize(array $payload, string $ip): array
    {
        if ($payload === [] || ! array_key_exists('ip', $payload)) {
            throw new \InvalidArgumentException('IP2Location payload contains no lookup result.');
        }

        $usageType = $this->firstText([$payload['usage_type'] ?? null, $payload['address_type'] ?? null]);
        $proxyType = $this->text($payload['proxy_type'] ?? null);
        $isProxy = $this->flagValue($payload['is_proxy'] ?? null);
        $isDatacenter = $this->flagValue($payload['is_datacenter'] ?? null);

        $signals = $this->emptySignals();
        $signals['country'] = $this->text($payload['country_name'] ?? null);
        $signals['country_code'] = $this->text($payload['country_code'] ?? null);
        $signals['region'] = $this->text($payload['region_name'] ?? null);
        $signals['city'] = $this->text($payload['city_name'] ?? null);
        $signals['postal_code'] = $this->text($payload['zip_code'] ?? null);
        $signals['latitude'] = $this->floatValue($payload['latitude'] ?? null);
        $signals['longitude'] = $this->floatValue($payload['longitude'] ?? null);
        $signals['timezone'] = $this->text($payload['time_zone'] ?? null);
        $signals['isp'] = $this->text($payload['isp'] ?? null);
        $signals['organisation'] = $this->firstText([$payload['as_name'] ?? null, $payload['domain'] ?? null]);
        $signals['asn'] = $this->asnNumber($payload['asn'] ?? null);
        $signals['asn_organisation'] = $this->text($payload['as_name'] ?? null);
        $signals['connection_type'] = $usageType;
        $signals['classification_hint'] = $this->classificationHint($usageType);
        $signals['datacenter'] = $isDatacenter;
        $signals['is_proxy'] = $isProxy ?? ($proxyType !== null ? true : null);
        $signals['is_vpn'] = $this->flagValue($payload['is_vpn'] ?? null);
        $signals['is_tor'] = $this->flagValue($payload['is_tor'] ?? null);
        $signals['is_mobile'] = $this->flagValue($payload['is_mobile'] ?? null);
        $signals['is_hosting_proxy'] = $isDatacenter;
        $signals['provider_risk'] = $this->intValue($payload['fraud_score'] ?? null);
        $signals['anonymous'] = $this->anonymousFrom([
            'vpn' => $signals['is_vpn'],
            'proxy' => $signals['is_proxy'],
            'tor' => $signals['is_tor'],
        ]);

        return $signals;
    }

    private function key(): string
    {
        return trim((string) config('traceless.ip_intelligence.providers.ip2location.key'));
    }
}
