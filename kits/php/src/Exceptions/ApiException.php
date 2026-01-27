<?php

namespace Conduitex\Sdk\Exceptions;

use Psr\Http\Message\ResponseInterface;

class ApiException extends ConduitexException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly ResponseInterface $response,
    ) {
        parent::__construct($message, $statusCode);
    }
}
