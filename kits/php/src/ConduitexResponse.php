<?php

namespace Conduitex\Sdk;

use Psr\Http\Message\ResponseInterface;

class ConduitexResponse
{
    public function __construct(private readonly ResponseInterface $response) {}

    public function status(): int
    {
        return $this->response->getStatusCode();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function headers(): array
    {
        return $this->response->getHeaders();
    }

    public function body(): string
    {
        return (string) $this->response->getBody();
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $decoded = json_decode($this->body(), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function raw(): ResponseInterface
    {
        return $this->response;
    }
}
