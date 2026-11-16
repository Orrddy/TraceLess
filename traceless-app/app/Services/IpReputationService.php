<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Network and location signals for an IP address.
 *
 * This is deliberately not called a "fraud score": a real fraud score needs a
 * dedicated provider with a key and a model behind it. What this returns is what
 * a plain IP intelligence endpoint can honestly tell you (city, region, country,
 * ASN/organisation, timezone), and the UI says so.
 */
class IpReputationService
{
    /**
     * @return array{ip: string, city: ?string, region: ?string, country: ?string, org: ?string, timezone: ?string}
     */
    public function lookup(string $ip): array
    {
        $endpoint = rtrim((string) config('traceless.ip_lookup.endpoint', 'https://ipinfo.io'), '/');
        $token = config('traceless.ip_lookup.token');

        $response = Http::timeout(8)
            ->acceptJson()
            ->get($endpoint.'/'.urlencode($ip).'/json', array_filter(['token' => $token]));

        if (! $response->successful()) {
            throw new RuntimeException(
                'The lookup service replied with status '.$response->status().'. Try again in a moment.'
            );
        }

        $data = (array) $response->json();

        if (empty($data['ip']) && empty($data['city']) && empty($data['country'])) {
            throw new RuntimeException('The lookup service returned no usable data for that address.');
        }

        return [
            'ip' => (string) ($data['ip'] ?? $ip),
            'city' => $data['city'] ?? null,
            'region' => $data['region'] ?? null,
            'country' => $data['country'] ?? null,
            'org' => $data['org'] ?? null,
            'timezone' => $data['timezone'] ?? null,
        ];
    }
}
