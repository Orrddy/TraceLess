<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Validates and normalizes the JSON report produced by the Python worker.
 *
 * The worker is treated as untrusted input: every field is length-limited,
 * allow-listed, or dropped. Nothing from the worker is persisted verbatim.
 */
class ScannerResultNormalizer
{
    public const FINDING_CATEGORIES = [
        'social_profile',
        'developer_profile',
        'data_broker',
        'public_paste',
        'public_avatar',
        'public_directory',
        'impersonation',
    ];

    public const SOURCE_OUTCOMES = ['found', 'not_found', 'unknown', 'error', 'skipped'];

    public const VERIFICATION_STATUSES = ['verified', 'unverified', 'demo'];

    /**
     * @return array{findings: array<int, array<string, mixed>>, sources: array<int, array<string, mixed>>}
     */
    public function normalize(array $payload, string $identifierType, string $identifier): array
    {
        if (($payload['status'] ?? null) !== 'success') {
            throw new InvalidArgumentException('Scanner worker did not return a successful report.');
        }

        $findings = [];
        foreach (array_slice((array) ($payload['findings'] ?? []), 0, (int) config('traceless.python.max_findings', 250)) as $row) {
            $finding = $this->finding($row, $identifierType, $identifier);
            if ($finding !== null) {
                $findings[] = $finding;
            }
        }

        $sources = [];
        foreach (array_slice((array) ($payload['sources'] ?? []), 0, 200) as $row) {
            $source = $this->source($row);
            if ($source !== null) {
                $sources[] = $source;
            }
        }

        return ['findings' => $findings, 'sources' => $sources];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function finding(mixed $row, string $identifierType, string $identifier): ?array
    {
        if (! is_array($row)) {
            return null;
        }

        $source = $this->text($row['source'] ?? null, 120);
        if ($source === null) {
            return null;
        }

        return [
            'source' => $source,
            'category' => $this->oneOf($row['category'] ?? null, self::FINDING_CATEGORIES, 'social_profile'),
            'identifier_type' => $this->text($row['identifier_type'] ?? null, 40) ?? $identifierType,
            'identifier_value' => $this->text($row['identifier_value'] ?? null, 255) ?? $identifier,
            'url' => $this->url($row['url'] ?? null),
            'confidence' => min(1, max(0, (float) ($row['confidence'] ?? 0.7))),
            'risk' => $this->oneOf($row['risk'] ?? null, ['low', 'moderate', 'high', 'critical'], 'moderate'),
            'removal_method' => $this->oneOf($row['removal_method'] ?? null, ['automated_request', 'manual_instructions'], 'manual_instructions'),
            'status' => 'needs_user_action',
            'title' => $this->text($row['title'] ?? null, 180) ?? 'Public exposure',
            'description' => $this->text($row['description'] ?? null, 2000),
            'action_guidance' => $this->text($row['action_guidance'] ?? null, 2000)
                ?? 'Review the source and use the available opt-out flow.',
            'verification_status' => $this->oneOf(
                $row['verification_status'] ?? null,
                self::VERIFICATION_STATUSES,
                'unverified'
            ),
            'evidence' => $this->evidence($row['evidence'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function source(mixed $row): ?array
    {
        if (! is_array($row)) {
            return null;
        }

        $name = $this->text($row['name'] ?? null, 120);
        if ($name === null) {
            return null;
        }

        return [
            'name' => $name,
            'category' => $this->oneOf($row['category'] ?? null, self::FINDING_CATEGORIES, 'social_profile'),
            'outcome' => $this->oneOf($row['outcome'] ?? null, self::SOURCE_OUTCOMES, 'unknown'),
            'checked_url' => $this->url($row['checked_url'] ?? null),
            'http_status' => $this->httpStatus($row['http_status'] ?? null),
            'rule' => $this->text($row['rule'] ?? null, 40),
            'reason' => $this->text($row['reason'] ?? null, 60),
            'elapsed_ms' => $this->elapsed($row['elapsed_ms'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function evidence(mixed $row): ?array
    {
        if (! is_array($row)) {
            return null;
        }

        $hash = $this->text($row['body_sha256'] ?? null, 64);

        return array_filter([
            'rule' => $this->text($row['rule'] ?? null, 40),
            'http_status' => $this->httpStatus($row['http_status'] ?? null),
            'checked_url' => $this->url($row['checked_url'] ?? null),
            'final_url' => $this->url($row['final_url'] ?? null),
            'body_sha256' => $hash !== null && preg_match('/^[a-f0-9]{64}$/', $hash) === 1 ? $hash : null,
            'elapsed_ms' => $this->elapsed($row['elapsed_ms'] ?? null),
            'checked_at' => $this->text($row['checked_at'] ?? null, 40),
        ], fn ($value) => $value !== null);
    }

    protected function text(mixed $value, int $limit): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $clean = trim(strip_tags($value));

        return $clean === '' ? null : mb_substr($clean, 0, $limit);
    }

    /**
     * @param  array<int, string>  $allowed
     */
    protected function oneOf(mixed $value, array $allowed, string $default): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }

    protected function url(mixed $value): ?string
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return str_starts_with($value, 'https://') || str_starts_with($value, 'http://')
            ? substr($value, 0, 2048)
            : null;
    }

    protected function httpStatus(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $status = (int) $value;

        return $status >= 100 && $status <= 599 ? $status : null;
    }

    protected function elapsed(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return max(0, min(600000, (int) $value));
    }
}
