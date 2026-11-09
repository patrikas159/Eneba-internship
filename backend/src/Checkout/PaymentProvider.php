<?php

declare(strict_types=1);

namespace App\Checkout;

/**
 * Port for whatever takes the money. The checkout only depends on this interface, so a real
 * PSP (Stripe, Adyen, ...) can replace the simulated one without touching order logic.
 */
interface PaymentProvider
{
    /** Short provider id stored on every payment row. */
    public function name(): string;

    /**
     * @param array<string, mixed> $options provider-specific input from the client (e.g. a test card)
     */
    public function charge(int $amountCents, string $currency, string $orderReference, array $options): PaymentResult;
}
