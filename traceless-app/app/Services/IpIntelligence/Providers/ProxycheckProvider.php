<?php

namespace App\Services\IpIntelligence\Providers;

use App\Services\IpIntelligence\IpProvider;
use App\Services\IpIntelligence\ParsesProviderPayload;

/**
 * proxycheck.io: anonymity and residential-proxy detection.
 *
 * The v2 endpoint is the "legacy" API in their documentation and is supported
 * until 2035. Its flat string schema is stable, which keeps the parser simple.
 *
 * https://proxycheck.io/api/
 */
class ProxycheckProvider implements IpProvider
{
    use ParsesProviderPayload;

    /**
     * v2 reports a type code or label; translate it without losing the raw value.
     */
    private const TYPE_FLAGS = [
        'vpn' => ['is_vpn' => true, 'classification_hint' => null],
        'pub' => ['is_proxy' => true, 'classification_hint' => null],
        'proxy' => ['is_proxy' => true, 'classification_hint' => null],
        'tor' => ['is_tor' => true, 'classification_hint' => null],
        'res' => ['is_residential_proxy' => true, 'classification_hint' => 'residential'],
        'residential' => ['is_residential_proxy' => true, 'classification_hint' => 'residential'],
        'dch' => ['is_hosting_proxy' => true, 'classification_hint' => 'hosting'],
        'hosting' => ['is_hosting_proxy' => true, 'classification_hint' => 'hosting'],
        'biz' => ['classification_hint' => 'business'],
        'business' => ['classification_hint' => 'business'],
        'com' => ['classification_hint' => 'business'],
        'wireless' => ['classification_hint' => 'mobile', 'is_mobile' => true],
        'mobile' => ['classification_hint' => 'mobile', 'is_mobile' => true],
    ];

    public function name(): string
    {
        return 'proxycheck';
    }

    public function label(): string
    {
        return 'proxycheck.io';
    }

    public function isConfigured(): bool
    {
        return $this->key() !== '';
    }

    public function url(string $ip): string
    {
        $base = rtrim((string) config('traceless.ip_intelligence.providers.proxycheck.endpoint'), '/');

        return $base.'/'.urlencode($ip).'?key='.urlencode($this->key()).'&vpn=1&asn=1&risk=1';
    }

    public function headers(): array
    {
        return [];
    }

    public function normalize(array $payload, string $ip): array
    {
        $status = strtolower((string) $this->text($payload['status'] ?? null));

        if ($status !== '' && ! in_array($status, ['ok', 'success', 'warning'], true)) {
            throw new \InvalidArgumentException('proxycheck.io returned status '.$status.'.');
        }

        $report = null;

        foreach ($payload as $name => $value) {
            if ($name !== 'status' && is_array($value)) {
                $report = $value;

                break;
            }
        }

        if ($report === null) {
            throw new \InvalidArgumentException('proxycheck.io payload contains no report.');
        }

        $type = $this->text($report['type'] ?? null);
        $typeLower = strtolower((string) $type);
        $proxyFlag = $this->flagValue($report['proxy'] ?? null);

        $signals = $this->emptySignals();
        $signals['country'] = $this->text($report['country'] ?? null);
        $signals['country_code'] = $this->text($report['isocode'] ?? null);
        $signals['region'] = $this->text($report['region'] ?? null);
        $signals['city'] = $this->text($report['city'] ?? null);
        $signals['postal_code'] = $this->text($report['postcode'] ?? null);
        $signals['latitude'] = $this->floatValue($report['latitude'] ?? null);
        $signals['longitude'] = $this->floatValue($report['longitude'] ?? null);
        $signals['timezone'] = $this->text($report['timezone'] ?? null);
        $signals['isp'] = $this->text($report['provider'] ?? null);
        $signals['organisation'] = $this->text($report['organisation'] ?? null);
        $signals['asn'] = $this->asnNumber($report['asn'] ?? null);
        $signals['network_range'] = $this->text($report['range'] ?? null);
        $signals['connection_type'] = $type;
        $signals['is_proxy'] = $proxyFlag;
        $signals['provider_risk'] = $this->intValue($report['risk'] ?? null);

        foreach (self::TYPE_FLAGS as $needle => $flags) {
            if (! str_contains($typeLower, $needle)) {
                continue;
            }

            foreach ($flags as $field => $value) {
                $signals[$field] = $value;
            }
        }

        if ($signals['classification_hint'] === null) {
            $signals['classification_hint'] = $this->classificationHint($type);
        }

        $signals['anonymous'] = $this->anonymousFrom([
            'vpn' => $signals['is_vpn'],
            'proxy' => $signals['is_proxy'],
            'tor' => $signals['is_tor'],
        ]);

        return $signals;
    }

    private function key(): string
    {
        return trim((string) config('traceless.ip_intelligence.providers.proxycheck.key'));
    }
}
