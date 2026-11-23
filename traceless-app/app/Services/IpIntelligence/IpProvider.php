<?php

namespace App\Services\IpIntelligence;

/**
 * A single upstream IP-intelligence provider.
 *
 * Implementations own their URL, headers and the mapping from the provider's
 * payload to the normalized signal array used by the merge and scoring steps.
 * They never touch the database or the cache.
 */
interface IpProvider
{
    /**
     * Config key under traceless.ip_intelligence.providers.
     */
    public function name(): string;

    /**
     * Public attribution label returned in the `sources` array.
     */
    public function label(): string;

    public function isConfigured(): bool;

    /**
     * Full request URL, including the provider key. Never log this value.
     */
    public function url(string $ip): string;

    /**
     * @return array<string, string>
     */
    public function headers(): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException when the payload is not a usable report
     */
    public function normalize(array $payload, string $ip): array;
}
