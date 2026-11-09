<?php

declare(strict_types=1);

namespace App\Checkout;

/**
 * A checkout rule was violated. Carries the JSON error code, HTTP status and extra fields
 * so controllers can turn it into a `{error, message, ...}` response unchanged.
 */
final class CheckoutException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus,
        public readonly array $extra = [],
    ) {
        parent::__construct($message);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['error' => $this->errorCode, 'message' => $this->getMessage()] + $this->extra;
    }
}
