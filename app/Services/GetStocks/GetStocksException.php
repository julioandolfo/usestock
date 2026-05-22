<?php

namespace App\Services\GetStocks;

use RuntimeException;
use Throwable;

class GetStocksException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly ?array $payload = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }

    public static function fromResponse(string $context, int $status, ?array $payload): self
    {
        $message = sprintf('GetStocks %s failed (HTTP %d): %s', $context, $status, self::extractMessage($payload));

        return new self($message, $status, $payload);
    }

    private static function extractMessage(?array $payload): string
    {
        if (! is_array($payload)) {
            return 'unknown error';
        }

        if (isset($payload['message']) && is_string($payload['message'])) {
            return $payload['message'];
        }

        if (isset($payload['message']) && is_array($payload['message'])) {
            return collect($payload['message'])->flatten()->implode(' / ');
        }

        if (isset($payload['data']) && is_string($payload['data'])) {
            return $payload['data'];
        }

        return json_encode($payload) ?: 'unknown error';
    }
}
