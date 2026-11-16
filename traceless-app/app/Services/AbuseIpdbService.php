<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Community abuse signal for a public IP address, from AbuseIPDB.
 *
 * The returned score is an abuse confidence figure: the share of reporters who
 * flagged the address recently. It is not a fraud score and it is not a verdict
 * on the person using the address, which is why the UI leans on the report count
 * and the usage type rather than the number alone.
 *
 * @see https://docs.abuseipdb.com/#check-endpoint
 */
class AbuseIpdbService
{
    /**
     * @return array{score: int, reports: int, distinct_reporters: int, usage_type: ?string, isp: ?string, country: ?string, is_tor: bool, is_whitelisted: bool, last_reported_at: ?string}
     */
    public function check(string $ip): array
    {
        $key = (string) config('traceless.ip_lookup.abuseipdb_key');

        if ($key === '') {
            throw new RuntimeException('AbuseIPDB is not configured.');
        }

        $endpoint = rtrim((string) config('traceless.ip_lookup.abuseipdb_endpoint', 'https://api.abuseipdb.com/api/v2'), '/');

        $response = Http::timeout(10)
            ->withHeaders(['Key' => $key, 'Accept' => 'application/json'])
            ->get($endpoint.'/check', [
                'ipAddress' => $ip,
                'maxAgeInDays' => 90,
            ]);

        if ($response->status() === 422) {
            throw new RuntimeException('AbuseIPDB only accepts public addresses. This one is private or reserved.');
        }

        if ($response->status() === 429) {
            throw new RuntimeException('The abuse service is rate limiting this key. Try again later.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('The abuse service replied with status '.$response->status().'.');
        }

        $data = (array) ($response->json('data') ?? []);

        if ($data === []) {
            throw new RuntimeException('The abuse service returned no data for that address.');
        }

        return [
            'score' => (int) ($data['abuseConfidenceScore'] ?? 0),
            'reports' => (int) ($data['totalReports'] ?? 0),
            'distinct_reporters' => (int) ($data['numDistinctUsers'] ?? 0),
            'usage_type' => $data['usageType'] ?? null,
            'isp' => $data['isp'] ?? null,
            'country' => $data['countryCode'] ?? null,
            'is_tor' => (bool) ($data['isTor'] ?? false),
            'is_whitelisted' => (bool) ($data['isWhitelisted'] ?? false),
            'last_reported_at' => $data['lastReportedAt'] ?? null,
        ];
    }
}
