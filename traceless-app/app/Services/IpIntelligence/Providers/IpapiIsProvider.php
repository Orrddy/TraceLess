<?php

namespace App\Services\IpIntelligence\Providers;

use App\Services\IpIntelligence\IpProvider;
use App\Services\IpIntelligence\ParsesProviderPayload;

/**
 * ipapi.is: the primary source for geolocation, ASN and hosting/VPN flags.
 *
 * https://ipapi.is/developers.html
 */
class IpapiIsProvider implements IpProvider
{
    use ParsesProviderPayload;

    public function name(): string
    {
        return 'ipapi_is';
    }

    public function label(): string
    {
        return 'ipapi.is';
    }

    public function isConfigured(): bool
    {
        return $this->key() !== '';
    }

    public function url(string $ip): string
    {
        $base = rtrim((string) config('traceless.ip_intelligence.providers.ipapi_is.endpoint'), '/');

        return $base.'/?q='.urlencode($ip).'&key='.urlencode($this->key());
    }

    public function headers(): array
    {
        return [];
    }

    public function normalize(array $payload, string $ip): array
    {
        if ($payload === [] || ! array_key_exists('ip', $payload)) {
            throw new \InvalidArgumentException('ipapi.is payload contains no lookup result.');
        }

        $location = is_array($payload['location'] ?? null) ? $payload['location'] : [];
        $asn = is_array($payload['asn'] ?? null) ? $payload['asn'] : [];
        $company = is_array($payload['company'] ?? null) ? $payload['company'] : [];
        $datacenter = is_array($payload['datacenter'] ?? null) ? $payload['datacenter'] : [];

        $connectionType = $this->firstText([$company['type'] ?? null, $asn['type'] ?? null]);
        $isMobile = $this->truthyFlag($payload['is_mobile'] ?? null);

        $signals = $this->emptySignals();
        $signals['country'] = $this->text($location['country'] ?? null);
        $signals['country_code'] = $this->text($location['country_code'] ?? null);
        $signals['region'] = $this->text($location['state'] ?? null);
        $signals['city'] = $this->text($location['city'] ?? null);
        $signals['postal_code'] = $this->text($location['zip'] ?? null);
        $signals['latitude'] = $this->floatValue($location['latitude'] ?? null);
        $signals['longitude'] = $this->floatValue($location['longitude'] ?? null);
        $signals['timezone'] = $this->text($location['timezone'] ?? null);
        $signals['isp'] = $this->text($company['name'] ?? null);
        $signals['organisation'] = $this->firstText([$asn['org'] ?? null, $company['name'] ?? null]);
        $signals['asn'] = $this->asnNumber($asn['asn'] ?? null);
        $signals['asn_organisation'] = $this->text($asn['org'] ?? null);
        $signals['network_range'] = $this->firstText([$asn['route'] ?? null, $datacenter['network'] ?? null]);
        $signals['connection_type'] = $connectionType;
        $signals['classification_hint'] = $isMobile === true ? 'mobile' : $this->classificationHint($connectionType);
        $signals['is_mobile'] = $isMobile;
        $signals['datacenter'] = $this->truthyFlag($payload['is_datacenter'] ?? null);
        $signals['is_vpn'] = $this->truthyFlag($payload['is_vpn'] ?? null);
        $signals['is_proxy'] = $this->truthyFlag($payload['is_proxy'] ?? null);
        $signals['is_tor'] = $this->truthyFlag($payload['is_tor'] ?? null);
        $signals['is_hosting_proxy'] = $signals['datacenter'];
        $signals['is_abuser'] = $this->truthyFlag($payload['is_abuser'] ?? null);
        $signals['anonymous'] = $this->anonymousFrom([
            'vpn' => $signals['is_vpn'],
            'proxy' => $signals['is_proxy'],
            'tor' => $signals['is_tor'],
        ]);

        return $signals;
    }

    private function key(): string
    {
        return trim((string) config('traceless.ip_intelligence.providers.ipapi_is.key'));
    }
}
