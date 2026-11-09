<?php

declare(strict_types=1);

namespace App\Checkout;

final class PaymentResult
{
    private function __construct(
        public readonly bool $succeeded,
        public readonly ?string $providerReference,
        public readonly ?string $failureReason,
    ) {
    }

    public static function success(string $providerReference): self
    {
        return new self(true, $providerReference, null);
    }

    public static function failure(string $reason): self
    {
        return new self(false, null, $reason);
    }
}
