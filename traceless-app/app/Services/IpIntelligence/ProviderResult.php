<?php

namespace App\Services\IpIntelligence;

/**
 * One provider's outcome for one IP analysis.
 *
 * `status` is part of the public endpoint contract (ok/error/timeout/unavailable).
 * `reason` is internal only and never returned to a browser.
 */
final class ProviderResult
{
    public const STATUS_OK = 'ok';

    public const STATUS_ERROR = 'error';

    public const STATUS_TIMEOUT = 'timeout';

    public const STATUS_UNAVAILABLE = 'unavailable';

    /**
     * @param  array<string, mixed>|null  $raw  Decoded provider payload (keys stripped).
     * @param  array<string, mixed>|null  $normalized  The subset the merge step reads.
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $label,
        public readonly string $status,
        public readonly ?array $raw = null,
        public readonly ?array $normalized = null,
        public readonly ?string $reason = null,
        public readonly ?int $httpStatus = null,
    ) {}

    public static function ok(string $provider, string $label, array $raw, array $normalized): self
    {
        return new self($provider, $label, self::STATUS_OK, $raw, $normalized);
    }

    public static function unavailable(string $provider, string $label): self
    {
        return new self($provider, $label, self::STATUS_UNAVAILABLE, reason: 'missing_api_key');
    }

    public static function failure(string $provider, string $label, string $status, string $reason, ?int $httpStatus = null): self
    {
        return new self($provider, $label, $status, reason: $reason, httpStatus: $httpStatus);
    }

    public function isOk(): bool
    {
        return $this->status === self::STATUS_OK;
    }

    /**
     * Temporary failures only. A reused key, a 4xx or a 429 must not be retried.
     */
    public function isRetryable(): bool
    {
        return $this->status === self::STATUS_TIMEOUT
            || ($this->status === self::STATUS_ERROR && in_array($this->reason, ['connection_error', 'http_5xx', 'invalid_payload'], true));
    }

    /**
     * @return array<string, mixed>
     */
    public function toStoredArray(): array
    {
        return array_filter([
            'status' => $this->status,
            'reason' => $this->reason,
            'http_status' => $this->httpStatus,
            'raw' => $this->raw,
        ], fn ($value) => $value !== null);
    }
}
