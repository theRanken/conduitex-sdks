<?php

namespace Conduitex\Sdk;

use Conduitex\Sdk\Exceptions\ApiException;
use Conduitex\Sdk\Exceptions\ConduitexException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class ConduitexClient
{
    private const DEFAULT_BASE_URL = 'https://api.conduitex.com';

    private ClientInterface $client;
    private string $baseUrl;

    public function __construct(
        private readonly string $vaultKey,
        private readonly string $apiVersion = 'v1',
        private readonly float $timeout = 30.0,
        ?ClientInterface $httpClient = null,
    ) {
        $this->baseUrl = $this->resolveBaseUrl();

        if (trim($vaultKey) === '') {
            throw new ConduitexException('vaultKey is required');
        }

        $this->client = $this->buildHttpClient($httpClient, $this->baseUrl);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    public function request(
        string $serviceSlug,
        string $method = 'GET',
        ?string $path = null,
        array $query = [],
        array|string|null $body = null,
        array $headers = [],
        ?string $idempotencyKey = null,
        ?float $timeout = null,
    ): ConduitexResponse {
        $uri = $this->buildPath($serviceSlug, $path);

        $options = [
            'headers' => $this->buildHeaders($headers, $idempotencyKey),
            'query' => $query,
        ];

        if (is_array($body)) {
            $options['json'] = $body;
        } elseif ($body !== null) {
            $options['body'] = $body;
        }

        if ($timeout !== null) {
            $options['timeout'] = $timeout;
        }

        try {
            $response = $this->client->request($method, $uri, $options);
        } catch (GuzzleException $exception) {
            throw new ConduitexException($exception->getMessage(), previous: $exception);
        }

        if ($response->getStatusCode() >= 400) {
            $message = $this->extractErrorMessage($response);

            throw new ApiException($message, $response->getStatusCode(), $response);
        }

        return new ConduitexResponse($response);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    public function get(
        string $serviceSlug,
        ?string $path = null,
        array $query = [],
        array $headers = [],
        ?string $idempotencyKey = null,
        ?float $timeout = null,
    ): ConduitexResponse {
        return $this->request(
            $serviceSlug,
            'GET',
            $path,
            $query,
            null,
            $headers,
            $idempotencyKey,
            $timeout,
        );
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    public function post(
        string $serviceSlug,
        ?string $path = null,
        array|string|null $body = null,
        array $query = [],
        array $headers = [],
        ?string $idempotencyKey = null,
        ?float $timeout = null,
    ): ConduitexResponse {
        return $this->request(
            $serviceSlug,
            'POST',
            $path,
            $query,
            $body,
            $headers,
            $idempotencyKey,
            $timeout,
        );
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    public function put(
        string $serviceSlug,
        ?string $path = null,
        array|string|null $body = null,
        array $query = [],
        array $headers = [],
        ?string $idempotencyKey = null,
        ?float $timeout = null,
    ): ConduitexResponse {
        return $this->request(
            $serviceSlug,
            'PUT',
            $path,
            $query,
            $body,
            $headers,
            $idempotencyKey,
            $timeout,
        );
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    public function patch(
        string $serviceSlug,
        ?string $path = null,
        array|string|null $body = null,
        array $query = [],
        array $headers = [],
        ?string $idempotencyKey = null,
        ?float $timeout = null,
    ): ConduitexResponse {
        return $this->request(
            $serviceSlug,
            'PATCH',
            $path,
            $query,
            $body,
            $headers,
            $idempotencyKey,
            $timeout,
        );
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    public function delete(
        string $serviceSlug,
        ?string $path = null,
        array $query = [],
        array $headers = [],
        ?string $idempotencyKey = null,
        ?float $timeout = null,
    ): ConduitexResponse {
        return $this->request(
            $serviceSlug,
            'DELETE',
            $path,
            $query,
            null,
            $headers,
            $idempotencyKey,
            $timeout,
        );
    }

    private function baseUri(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    private function buildPath(string $serviceSlug, ?string $path): string
    {
        $cleanService = trim($serviceSlug, '/ ');
        $cleanPath = $path === null ? '' : trim($path, '/ ');
        $prefix = 'api/'.trim($this->apiVersion, '/').'/proxy/'.$cleanService;

        return $cleanPath === ''
            ? $prefix
            : "{$prefix}/{$cleanPath}";
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function buildHeaders(array $headers, ?string $idempotencyKey): array
    {
        $baseHeaders = [
            'X-Vault-Key' => $this->vaultKey,
            'Accept' => 'application/json',
        ];

        if ($idempotencyKey !== null && trim($idempotencyKey) !== '') {
            $baseHeaders['Idempotency-Key'] = $idempotencyKey;
        }

        return [
            ...$baseHeaders,
            ...$headers,
        ];
    }

    private function extractErrorMessage(ResponseInterface $response): string
    {
        $payload = json_decode((string) $response->getBody(), true);

        if (is_array($payload) && isset($payload['message']) && is_string($payload['message']) && trim($payload['message']) !== '') {
            return $payload['message'];
        }

        return sprintf('Request failed with status %s', $response->getStatusCode());
    }

    private function resolveBaseUrl(): string
    {
        $candidate = getenv('CONDUITEX_BASE_URL')
            ?: self::DEFAULT_BASE_URL;

        if ($candidate === null || trim($candidate) === '') {
            throw new ConduitexException('CONDUITEX_BASE_URL is required.');
        }

        return rtrim($candidate, '/');
    }

    private function buildHttpClient(?ClientInterface $httpClient, string $baseUrl): ClientInterface
    {
        $defaults = $this->defaultClientConfig($baseUrl);

        if ($httpClient instanceof Client) {
            $config = $httpClient->getConfig();
            $mergedHeaders = [
                ...$defaults['headers'],
                ...($config['headers'] ?? []),
            ];

            $mergedConfig = [
                ...$config,
                'base_uri' => $config['base_uri'] ?? $defaults['base_uri'],
                'timeout' => $config['timeout'] ?? $defaults['timeout'],
                'http_errors' => false,
                'headers' => $mergedHeaders,
            ];

            return new Client($mergedConfig);
        }

        if ($httpClient !== null) {
            return $httpClient;
        }

        return new Client($defaults);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultClientConfig(string $baseUrl): array
    {
        return [
            'base_uri' => rtrim($baseUrl, '/'),
            'timeout' => $this->timeout,
            'http_errors' => false,
            'headers' => [
                'X-Vault-Key' => $this->vaultKey,
                'Accept' => 'application/json',
            ],
        ];
    }
}
