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
 *
 * Private and reserved addresses (127.0.0.1, 10/8, 192.168/16, ::1) have no
 * public record. In that case the lookup falls back to the public address the
 * network is actually using, which is what a person on their own machine means
 * when they ask "what does my IP reveal".
 */
class IpReputationService
{
    /**
     * @return array{ip: string, city: ?string, region: ?string, country: ?string, org: ?string, timezone: ?string, auto: bool}
     */
    public function lookup(?string $ip = null): array
    {
        $ip = $ip !== null ? trim($ip) : null;
        $public = $ip !== null && $this->isPublic($ip) ? $ip : null;

        $endpoint = rtrim((string) config('traceless.ip_lookup.endpoint', 'https://ipinfo.io'), '/');
        $token = config('traceless.ip_lookup.token');
        $url = $endpoint.($public !== null ? '/'.urlencode($public) : '').'/json';

        $response = Http::timeout(10)
            ->acceptJson()
            ->get($url, array_filter(['token' => $token]));

        if (! $response->successful()) {
            $message = $response->status() === 429
                ? 'The lookup service is rate limiting this network. Try again in a minute.'
                : 'The lookup service replied with status '.$response->status().'. Try again in a moment.';

            throw new RuntimeException($message);
        }

        $data = (array) $response->json();

        if (empty($data['ip']) && empty($data['country'])) {
            throw new RuntimeException('The lookup service returned no usable data for that address.');
        }

        return [
            'ip' => (string) ($data['ip'] ?? $public ?? $ip ?? 'unknown'),
            'city' => $data['city'] ?? null,
            'region' => $data['region'] ?? null,
            'country' => $data['country'] ?? null,
            'org' => $data['org'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'auto' => $public === null,
        ];
    }

    private function isPublic(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
