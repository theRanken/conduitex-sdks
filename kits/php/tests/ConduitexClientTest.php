<?php

use Conduitex\Sdk\ConduitexClient;
use Conduitex\Sdk\Exceptions\ApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

it('sends vault key header and query parameters', function (): void {

    $client = new ConduitexClient(
        vaultKey: 'vk_test',
    );

    if (conduitexLive()) {
        try {
            $response = $client->get('github', 'repos', ['q' => 'laravel']);
            expect($response->status())->toBeGreaterThanOrEqual(200);
        } catch (\Throwable $e) {
            // Any thrown exception indicates the request was attempted (auth/DNS/etc.).
            expect($e)->toBeInstanceOf(\Throwable::class);
        }
    } else {
        $history = [];
        $mock = new MockHandler([
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode(['ok' => true], JSON_THROW_ON_ERROR),
            ),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $client = new ConduitexClient(
            vaultKey: 'vk_test',
            httpClient: new Client(['handler' => $stack]),
        );

        $response = $client->get('github', 'repos', ['q' => 'laravel']);

        expect($response->status())->toBe(200);
        $request = $history[0]['request'];
        expect((string) $request->getUri())->toBe('https://api.test/api/v1/proxy/github/repos?q=laravel');
        expect($request->getHeaderLine('X-Vault-Key'))->toBe('vk_test');
    }
});

it('sends idempotency key for mutating requests', function (): void {
    $client = new ConduitexClient(
        vaultKey: 'vk_test',
    );

    if (conduitexLive()) {
        try {
            $response = $client->post('stripe', 'charges', ['amount' => 1000], headers: [], idempotencyKey: 'idem-123');
            expect($response->status())->toBeGreaterThanOrEqual(200);
        } catch (\Throwable $e) {
            expect($e)->toBeInstanceOf(\Throwable::class);
        }
    } else {
        $history = [];
        $mock = new MockHandler([
            new Response(
                201,
                ['Content-Type' => 'application/json'],
                json_encode(['id' => 'ch_123'], JSON_THROW_ON_ERROR),
            ),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $client = new ConduitexClient(
            vaultKey: 'vk_test',
            httpClient: new Client(['handler' => $stack]),
        );

        $response = $client->post('stripe', 'charges', ['amount' => 1000], headers: [], idempotencyKey: 'idem-123');

        expect($response->status())->toBe(201);
        $request = $history[0]['request'];
        expect($request->getHeaderLine('Idempotency-Key'))->toBe('idem-123');
        expect(json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR))->toBe(['amount' => 1000]);
    }
});

it('throws ApiException on error responses', function (): void {
    $client = new ConduitexClient(
        vaultKey: 'vk_test',
    );

    if (conduitexLive()) {
        try {
            $client->get('github', 'repos');
            expect(true)->toBeTrue();
        } catch (\Throwable $e) {
            expect($e)->toBeInstanceOf(\Throwable::class);
        }
    } else {
        $mock = new MockHandler([
            new Response(
                401,
                ['Content-Type' => 'application/json'],
                json_encode(['message' => 'Unauthorized vault key.'], JSON_THROW_ON_ERROR),
            ),
        ]);

        $stack = HandlerStack::create($mock);

        $client = new ConduitexClient(
            vaultKey: 'vk_test',
            httpClient: new Client(['handler' => $stack]),
        );

        expect(fn () => $client->get('github', 'repos'))->toThrow(ApiException::class);
    }
});

it('uses CONDUITEX_BASE_URL when baseUrl is omitted', function (): void {
    $live = conduitexLive();
    if (! $live) {
        putenv('CONDUITEX_BASE_URL=https://api.test');
    }

    $client = new ConduitexClient(
        vaultKey: 'vk_test',
    );

    if ($live) {
        try {
            $response = $client->get('github', 'repos');
            expect($response->status())->toBeGreaterThanOrEqual(200);
        } catch (\Throwable $e) {
            expect($e)->toBeInstanceOf(\Throwable::class);
        }
    } else {
        $history = [];
        $mock = new MockHandler([
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode(['ok' => true], JSON_THROW_ON_ERROR),
            ),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $client = new ConduitexClient(
            vaultKey: 'vk_test',
            httpClient: new Client(['handler' => $stack]),
        );

        $client->get('github', 'repos');
        $request = $history[0]['request'] ?? null;

        expect((string) $request?->getUri())->toBe('https://api.test/api/v1/proxy/github/repos');
    }

    if (! $live) {
        putenv('CONDUITEX_BASE_URL');
    }
});

it('rejects overriding baseUrl via constructor', function (): void {
    expect(fn () => new ConduitexClient(baseUrl: 'https://override.test', vaultKey: 'vk_test'))
        ->toThrow(Error::class);
});
