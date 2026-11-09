<?php

declare(strict_types=1);

namespace App\Checkout;

/**
 * Demo provider: every charge succeeds, except the well-known "declined" test card
 * 4000 0000 0000 0002, which lets the UI exercise the failure path.
 */
final class SimulatedPaymentProvider implements PaymentProvider
{
    public const DECLINED_TEST_CARD = '4000000000000002';

    public function name(): string
    {
        return 'simulated';
    }

    public function charge(int $amountCents, string $currency, string $orderReference, array $options): PaymentResult
    {
        $card = preg_replace('/\D/', '', (string) ($options['cardNumber'] ?? ''));
        if (self::DECLINED_TEST_CARD === $card) {
            return PaymentResult::failure('Card declined (simulated test card).');
        }

        return PaymentResult::success('sim_'.bin2hex(random_bytes(12)));
    }
}
