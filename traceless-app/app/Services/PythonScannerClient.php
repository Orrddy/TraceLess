<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Secure boundary around the Python reconnaissance worker.
 *
 * The worker is invoked with a fixed argument array (no shell interpolation),
 * receives only a public username, and must return a normalized JSON report.
 * Anything the worker emits is treated as untrusted input and normalized.
 */
class PythonScannerClient
{
    public function __construct(protected ScannerResultNormalizer $normalizer) {}

    /**
     * Findings only. Kept for callers that do not need source telemetry.
     *
     * @return array<int, array<string, mixed>>
     */
    public function scan(string $identifierType, string $identifier, bool $consent): array
    {
        return $this->scanReport($identifierType, $identifier, $consent)['findings'];
    }

    /**
     * Full report: verified findings plus a per-source outcome list.
     *
     * @param  int|null  $timeoutSeconds  Optional cap for this single worker
     *                                    process, used to keep a deep scan inside
     *                                    its wall-clock budget.
     * @return array{findings: array<int, array<string, mixed>>, sources: array<int, array<string, mixed>>}
     */
    public function scanReport(
        string $identifierType,
        string $identifier,
        bool $consent,
        string $mode = 'curated',
        ?int $timeoutSeconds = null,
        ?int $siteLimit = null,
    ): array {
        if (! $consent) {
            throw new RuntimeException('Consent is required before scanning.');
        }

        if (! in_array($identifierType, ['username', 'email', 'phone'], true)) {
            throw new RuntimeException('This identifier type cannot be verified by the scanner.');
        }

        $script = config('traceless.python.scanner');
        if (! is_string($script) || ! is_file($script)) {
            throw new RuntimeException('Scanner worker is not installed.');
        }

        $deep = $mode === 'deep';
        $arguments = [
            config('traceless.python.binary', 'python'),
            $script,
            $identifierType,
            $identifier,
            'true',
        ];

        if ($deep) {
            $arguments[] = '--deep';
        }

        if ($siteLimit !== null) {
            $arguments[] = '--limit';
            $arguments[] = (string) max(1, min(50, $siteLimit));
        }

        $defaultTimeout = (int) config($deep ? 'traceless.python.deep_timeout' : 'traceless.python.timeout', $deep ? 420 : 90);
        $timeout = $timeoutSeconds === null ? $defaultTimeout : max(1, min($defaultTimeout, $timeoutSeconds));

        $result = Process::timeout($timeout)
            ->run($arguments);

        if (! $result->successful()) {
            Log::warning('python_scanner_failed', [
                'exit_code' => $result->exitCode(),
                // Never log raw identifiers or full stderr in production.
                'stderr_hash' => hash('sha256', $result->errorOutput()),
            ]);

            throw new RuntimeException('Scanner worker did not complete successfully.');
        }

        $decoded = json_decode($result->output(), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Scanner worker returned an invalid payload.');
        }

        if (($decoded['status'] ?? null) !== 'success') {
            Log::warning('python_scanner_rejected', [
                'reason' => substr((string) ($decoded['message'] ?? 'unknown'), 0, 120),
            ]);

            throw new RuntimeException('Scanner worker could not verify this identifier.');
        }

        return $this->normalizer->normalize($decoded, $identifierType, $identifier);
    }
}
