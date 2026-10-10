<?php

namespace App\Modules\PaymentProviders\Support;

use App\Modules\PaymentProviders\Exceptions\ProviderConfigurationException;
use App\Modules\PaymentProviders\Exceptions\ProviderRequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin wrapper around Laravel's HTTP client that normalises provider failures
 * into ProviderRequestException. Every call sets an explicit timeout.
 */
class ProviderHttp
{
    public function __construct(private readonly string $provider) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    public function post(string $url, array $payload, array $headers = [], int $timeout = 30): array
    {
        return $this->decode(fn (): array => Http::withHeaders($headers)
            ->timeout($timeout)
            ->asJson()
            ->post($url, $payload)
            ->throw()
            ->json() ?? []);
    }

    /**
     * Posts form-encoded data and returns the decoded JSON response.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    public function postForm(string $url, array $payload, array $headers = [], int $timeout = 30): array
    {
        return $this->decode(fn (): array => Http::withHeaders($headers)
            ->timeout($timeout)
            ->asForm()
            ->post($url, $payload)
            ->throw()
            ->json() ?? []);
    }

    /**
     * Posts a raw body (used by signable/JSON-string requests) and returns the
     * decoded JSON response.
     *
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    public function postBody(string $url, string $body, array $headers, int $timeout = 30): array
    {
        $contentType = $headers['Content-Type'] ?? 'application/json';

        return $this->decode(fn (): array => Http::withHeaders($headers)
            ->timeout($timeout)
            ->withBody($body, $contentType)
            ->post($url)
            ->throw()
            ->json() ?? []);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    public function get(string $url, array $query = [], array $headers = [], int $timeout = 30): array
    {
        return $this->decode(fn (): array => Http::withHeaders($headers)
            ->timeout($timeout)
            ->get($url, $query)
            ->throw()
            ->json() ?? []);
    }

    /**
     * @param  callable(): array<string, mixed>  $call
     * @return array<string, mixed>
     */
    private function decode(callable $call): array
    {
        try {
            return $call();
        } catch (ConnectionException $exception) {
            throw new ProviderRequestException($this->provider, $exception->getMessage(), true);
        } catch (ProviderConfigurationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $message = $exception->getMessage();
            $timeout = str_contains($message, 'timed out') || str_contains($message, 'timeout');

            throw new ProviderRequestException($this->provider, $message, $timeout);
        }
    }
}
