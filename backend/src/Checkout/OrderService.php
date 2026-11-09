<?php

declare(strict_types=1);

namespace App\Checkout;

use App\Entity\Offer;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Write side of checkout.
 *
 * Invariants:
 *  - stock is the number of keys that are `available`, or `reserved` with an expired reservation;
 *  - placing an order reserves concrete keys (row locks with SKIP LOCKED, so concurrent buyers
 *    never get the same key and never block each other);
 *  - paying is idempotent per `Idempotency-Key` and serialised per order (`SELECT ... FOR UPDATE`);
 *  - prices always come from the `offers` table, never from the client.
 */
final class OrderService
{
    public const RESERVATION_MINUTES = 15;
    public const MAX_LINES = 20;
    public const MAX_QUANTITY = 10;

    private const REFERENCE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $connection,
        private readonly PaymentProvider $paymentProvider,
    ) {
    }

    /**
     * @param array<int, int> $lines offerId => quantity (already validated, duplicates merged)
     *
     * @throws CheckoutException
     */
    public function placeOrder(User $user, array $lines): string
    {
        /** @var array<int, Offer> $offers */
        $offers = [];
        foreach ($this->em->createQuery('SELECT o, p, s FROM App\Entity\Offer o JOIN o.product p JOIN o.seller s WHERE o.id IN (:ids)')
            ->setParameter('ids', array_keys($lines))
            ->getResult() as $offer) {
            $offers[$offer->getId()] = $offer;
        }

        $currency = null;
        foreach (array_keys($lines) as $offerId) {
            $offer = $offers[$offerId] ?? null;
            if (null === $offer || !$offer->isActive()) {
                throw new CheckoutException('offer_unavailable', 'This offer is no longer available.', 409, ['offerId' => $offerId]);
            }
            if (null !== $currency && $currency !== $offer->getCurrency()) {
                throw new CheckoutException('mixed_currency', 'All cart items must be in the same currency.', 422);
            }
            $currency = $offer->getCurrency();
        }

        $now = new \DateTimeImmutable();
        $reservedUntil = $now->modify(\sprintf('+%d minutes', self::RESERVATION_MINUTES));

        $this->connection->beginTransaction();
        try {
            $order = new Order($this->newReference(), $user, 0, (string) $currency);
            $total = 0;
            $reservations = [];
            foreach ($lines as $offerId => $quantity) {
                $offer = $offers[$offerId];
                $keyIds = $this->connection->fetchFirstColumn(
                    'SELECT id FROM product_keys
                     WHERE offer_id = :offer
                       AND (status = \'available\' OR (status = \'reserved\' AND reserved_until < :now))
                     ORDER BY id
                     LIMIT :qty
                     FOR UPDATE SKIP LOCKED',
                    ['offer' => $offerId, 'now' => $now, 'qty' => $quantity],
                    ['offer' => ParameterType::INTEGER, 'now' => Types::DATETIME_IMMUTABLE, 'qty' => ParameterType::INTEGER],
                );
                if (\count($keyIds) < $quantity) {
                    throw new CheckoutException('out_of_stock', \sprintf('Only %d key(s) left for %s from this seller.', \count($keyIds), $offer->getProduct()->getName()), 409, ['offerId' => $offerId, 'available' => \count($keyIds)]);
                }

                $item = new OrderItem($order, $offer, $offer->getProduct()->getName(), $offer->getPriceCents(), $quantity);
                $order->addItem($item);
                $total += $offer->getPriceCents() * $quantity;
                $reservations[] = [$item, $keyIds];
            }
            $order->setTotalCents($total);
            $this->em->persist($order);
            $this->em->flush();

            foreach ($reservations as [$item, $keyIds]) {
                $this->connection->executeStatement(
                    'UPDATE product_keys SET status = \'reserved\', reserved_until = :until, order_item_id = :item WHERE id IN (:ids)',
                    ['until' => $reservedUntil, 'item' => $item->getId(), 'ids' => $keyIds],
                    ['until' => Types::DATETIME_IMMUTABLE, 'item' => ParameterType::INTEGER, 'ids' => ArrayParameterType::INTEGER],
                );
            }
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            $this->em->clear();
            throw $e;
        }

        return $order->getReference();
    }

    /**
     * Charges a pending order. Replaying the same idempotency key returns the stored outcome
     * instead of charging again.
     *
     * @param array<string, mixed> $options passed through to the payment provider
     *
     * @return array{succeeded: bool, replayed: bool, failureReason: ?string}
     *
     * @throws CheckoutException
     */
    public function pay(User $user, string $reference, string $idempotencyKey, array $options): array
    {
        $this->expireStale((int) $user->getId());

        $this->connection->beginTransaction();
        try {
            $order = $this->connection->fetchAssociative(
                'SELECT id, status, total_cents, currency FROM orders WHERE reference = :ref AND user_id = :uid FOR UPDATE',
                ['ref' => $reference, 'uid' => $user->getId()],
            );
            if (false === $order) {
                throw self::orderNotFound();
            }
            $orderId = (int) $order['id'];

            $previous = $this->connection->fetchAssociative(
                'SELECT order_id, status, failure_reason FROM payments WHERE idempotency_key = :key',
                ['key' => $idempotencyKey],
            );
            if (false !== $previous) {
                $this->connection->commit();
                if ((int) $previous['order_id'] !== $orderId) {
                    throw new CheckoutException('idempotency_key_reused', 'This Idempotency-Key was already used for a different order.', 422);
                }

                return ['succeeded' => 'succeeded' === $previous['status'], 'replayed' => true, 'failureReason' => $previous['failure_reason']];
            }

            if ('paid' === $order['status']) {
                throw new CheckoutException('order_already_paid', 'This order has already been paid.', 409);
            }
            if ('pending' !== $order['status']) {
                throw new CheckoutException('order_not_payable', 'This order expired or was cancelled. Please place a new order.', 409, ['status' => $order['status']]);
            }

            $now = new \DateTimeImmutable();
            $needed = (int) $this->connection->fetchOne('SELECT coalesce(sum(quantity), 0) FROM order_items WHERE order_id = :order', ['order' => $orderId]);
            $held = (int) $this->connection->fetchOne(
                'SELECT count(*) FROM product_keys k JOIN order_items i ON i.id = k.order_item_id
                 WHERE i.order_id = :order AND k.status = \'reserved\' AND k.reserved_until > :now',
                ['order' => $orderId, 'now' => $now],
                ['order' => ParameterType::INTEGER, 'now' => Types::DATETIME_IMMUTABLE],
            );
            if ($held < $needed) {
                $this->cancelOrders([$orderId]);
                $this->connection->commit();
                throw new CheckoutException('reservation_expired', 'Your reservation expired. Please place a new order.', 409);
            }

            // The simulated provider is instant, so charging inside the transaction is fine; with a
            // real PSP we would persist a `pending` payment first and confirm it via webhook.
            $result = $this->paymentProvider->charge((int) $order['total_cents'], (string) $order['currency'], $reference, $options);

            $this->connection->insert('payments', [
                'order_id' => $orderId,
                'provider' => $this->paymentProvider->name(),
                'idempotency_key' => $idempotencyKey,
                'status' => $result->succeeded ? 'succeeded' : 'failed',
                'amount_cents' => (int) $order['total_cents'],
                'provider_reference' => $result->providerReference,
                'failure_reason' => $result->failureReason,
                'created_at' => $now,
            ], ['created_at' => Types::DATETIME_IMMUTABLE]);

            if ($result->succeeded) {
                $this->connection->executeStatement(
                    'UPDATE product_keys SET status = \'sold\', reserved_until = NULL
                     WHERE order_item_id IN (SELECT id FROM order_items WHERE order_id = :order)',
                    ['order' => $orderId],
                );
                $this->connection->update('orders', ['status' => 'paid', 'paid_at' => $now], ['id' => $orderId], ['paid_at' => Types::DATETIME_IMMUTABLE]);
            }
            // A declined payment leaves the order pending: the keys stay reserved until expiry
            // and the buyer may retry with a new Idempotency-Key.
            $this->connection->commit();

            return ['succeeded' => $result->succeeded, 'replayed' => false, 'failureReason' => $result->failureReason];
        } catch (UniqueConstraintViolationException) {
            $this->rollBackIfActive();
            // Two concurrent requests with one key for two different orders: one of them lost.
            throw new CheckoutException('idempotency_key_reused', 'This Idempotency-Key was already used for a different order.', 422);
        } catch (\Throwable $e) {
            $this->rollBackIfActive();
            throw $e;
        }
    }

    /**
     * Cancels pending orders whose reservation window has passed and frees their keys.
     * Called lazily before reads/payments and from `app:orders:expire`.
     *
     * @return int number of orders cancelled
     */
    public function expireStale(?int $userId = null): int
    {
        $cutoff = (new \DateTimeImmutable())->modify(\sprintf('-%d minutes', self::RESERVATION_MINUTES));
        $sql = 'SELECT id FROM orders WHERE status = \'pending\' AND created_at < :cutoff';
        $params = ['cutoff' => $cutoff];
        $types = ['cutoff' => Types::DATETIME_IMMUTABLE];
        if (null !== $userId) {
            $sql .= ' AND user_id = :uid';
            $params['uid'] = $userId;
        }

        return $this->connection->transactional(function () use ($sql, $params, $types): int {
            // SKIP LOCKED: an order that is being paid right now is left alone.
            $ids = array_map('intval', $this->connection->fetchFirstColumn($sql.' FOR UPDATE SKIP LOCKED', $params, $types));
            if ([] !== $ids) {
                $this->cancelOrders($ids);
            }

            return \count($ids);
        });
    }

    /**
     * @param list<int> $orderIds
     */
    private function cancelOrders(array $orderIds): void
    {
        $this->connection->executeStatement(
            'UPDATE product_keys SET status = \'available\', reserved_until = NULL, order_item_id = NULL
             WHERE status = \'reserved\' AND order_item_id IN (SELECT id FROM order_items WHERE order_id IN (:ids))',
            ['ids' => $orderIds],
            ['ids' => ArrayParameterType::INTEGER],
        );
        $this->connection->executeStatement(
            'UPDATE orders SET status = \'cancelled\' WHERE id IN (:ids) AND status = \'pending\'',
            ['ids' => $orderIds],
            ['ids' => ArrayParameterType::INTEGER],
        );
    }

    private function rollBackIfActive(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
    }

    public static function orderNotFound(): CheckoutException
    {
        return new CheckoutException('not_found', 'Order not found.', 404);
    }

    private function newReference(): string
    {
        $alphabet = self::REFERENCE_ALPHABET;
        $ref = 'ENB-';
        for ($i = 0; $i < 10; ++$i) {
            $ref .= $alphabet[random_int(0, \strlen($alphabet) - 1)];
        }

        return $ref;
    }
}
