<?php

namespace App\Services\IpIntelligence;

use App\Services\IpIntelligence\Providers\AbuseIpdbProvider;
use App\Services\IpIntelligence\Providers\Ip2locationProvider;
use App\Services\IpIntelligence\Providers\IpapiIsProvider;
use App\Services\IpIntelligence\Providers\ProxycheckProvider;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Runs the configured providers concurrently, with one bounded retry round for
 * temporary failures.
 *
 * Concurrency shape: all providers go out in a single Http::pool. Providers that
 * time out, return 5xx or return an unparsable body are retried once after a
 * short backoff. 4xx responses and HTTP 429 are final: retrying a rate-limited
 * key only burns more quota.
 */
class ProviderRunner
{
    use ParsesProviderPayload;

    public function __construct(private IpIntelligenceMetrics $metrics) {}

    /**
     * @return array<string, ProviderResult> keyed by provider name, always all four
     */
    public function gather(string $ip): array
    {
        $providers = $this->providers();
        $results = [];
        $pending = [];

        foreach ($providers as $name => $provider) {
            if (! $provider->isConfigured()) {
                $results[$name] = ProviderResult::unavailable($name, $provider->label());
                $this->metrics->recordProviderUnavailable($name);

                continue;
            }

            $pending[$name] = $provider;
        }

        $maxAttempts = max(1, (int) config('traceless.ip_intelligence.max_attempts', 2));
        $attempt = 0;

        while ($pending !== [] && $attempt < $maxAttempts) {
            if ($attempt > 0) {
                $this->backoff();
            }

            $round = $this->sendRound($ip, $pending);
            $retry = [];

            foreach ($round as $name => $result) {
                $lastAttempt = ($attempt + 1) >= $maxAttempts;

                if ($result->isOk() || ! $result->isRetryable() || $lastAttempt) {
                    $results[$name] = $result;

                    continue;
                }

                $retry[$name] = $pending[$name];
            }

            $pending = $retry;
            $attempt++;
        }

        foreach ($results as $name => $result) {
            $this->recordFinalMetrics($name, $result);
        }

        return $results;
    }

    /**
     * @param  array<string, IpProvider>  $providers
     * @return array<string, ProviderResult>
     */
    private function sendRound(string $ip, array $providers): array
    {
        $timeout = max(1.0, (float) config('traceless.ip_intelligence.timeout_seconds', 3));
        $connectTimeout = max(1.0, (float) config('traceless.ip_intelligence.connect_timeout_seconds', 2));

        $responses = [];
        $poolFailure = null;

        try {
            $responses = Http::pool(function (Pool $pool) use ($providers, $ip, $timeout, $connectTimeout) {
                foreach ($providers as $name => $provider) {
                    $request = $pool->as($name)
                        ->acceptJson()
                        ->timeout($timeout)
                        ->connectTimeout($connectTimeout);

                    if (($headers = $provider->headers()) !== []) {
                        $request->withHeaders($headers);
                    }

                    $this->metrics->recordProviderCall($name);

                    $request->get($provider->url($ip));
                }
            }, concurrency: 4);
        } catch (Throwable $exception) {
            $poolFailure = $exception;
        }

        $results = [];

        foreach ($providers as $name => $provider) {
            $results[$name] = $this->interpret($provider, $responses[$name] ?? $poolFailure, $ip);
        }

        return $results;
    }

    private function interpret(IpProvider $provider, mixed $response, string $ip): ProviderResult
    {
        if ($response instanceof Throwable) {
            return $this->failureFromException($provider, $response);
        }

        if (! $response instanceof Response) {
            return ProviderResult::failure($provider->name(), $provider->label(), ProviderResult::STATUS_ERROR, 'no_response');
        }

        $status = $response->status();

        if ($status === 429) {
            return ProviderResult::failure($provider->name(), $provider->label(), ProviderResult::STATUS_ERROR, 'rate_limited', 429);
        }

        if ($status >= 500) {
            return ProviderResult::failure($provider->name(), $provider->label(), ProviderResult::STATUS_ERROR, 'http_5xx', $status);
        }

        if (! $response->successful()) {
            return ProviderResult::failure($provider->name(), $provider->label(), ProviderResult::STATUS_ERROR, 'http_'.$status, $status);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return ProviderResult::failure($provider->name(), $provider->label(), ProviderResult::STATUS_ERROR, 'invalid_payload', $status);
        }

        try {
            $normalized = $provider->normalize($payload, $ip);
        } catch (Throwable) {
            return ProviderResult::failure($provider->name(), $provider->label(), ProviderResult::STATUS_ERROR, 'invalid_payload', $status);
        }

        return ProviderResult::ok($provider->name(), $provider->label(), $this->withoutSecrets($payload), $normalized);
    }

    private function failureFromException(IpProvider $provider, Throwable $exception): ProviderResult
    {
        $message = strtolower($exception->getMessage());

        $timedOut = str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'curl error 28');

        if ($exception instanceof ConnectionException || $exception instanceof ConnectException || $timedOut) {
            return $timedOut
                ? ProviderResult::failure($provider->name(), $provider->label(), ProviderResult::STATUS_TIMEOUT, 'timeout')
                : ProviderResult::failure($provider->name(), $provider->label(), ProviderResult::STATUS_ERROR, 'connection_error');
        }

        return ProviderResult::failure($provider->name(), $provider->label(), ProviderResult::STATUS_ERROR, 'connection_error');
    }

    private function recordFinalMetrics(string $name, ProviderResult $result): void
    {
        match ($result->status) {
            ProviderResult::STATUS_OK => null,
            ProviderResult::STATUS_TIMEOUT => $this->metrics->recordProviderTimeout($name),
            default => $result->reason === 'rate_limited'
                ? $this->metrics->recordRateLimited($name)
                : $this->metrics->recordProviderError($name),
        };
    }

    private function backoff(): void
    {
        $backoffMs = max(0, (int) config('traceless.ip_intelligence.retry_backoff_ms', 250));

        if ($backoffMs > 0) {
            usleep($backoffMs * 1000);
        }
    }

    /**
     * @return array<string, IpProvider>
     */
    private function providers(): array
    {
        return [
            'ipapi_is' => new IpapiIsProvider,
            'proxycheck' => new ProxycheckProvider,
            'abuseipdb' => new AbuseIpdbProvider,
            'ip2location' => new Ip2locationProvider,
        ];
    }
}
