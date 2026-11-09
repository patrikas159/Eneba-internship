<?php

declare(strict_types=1);

namespace App\Checkout;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Order JSON for the buyer. Key codes are only ever exposed for paid orders, and only to
 * the order's owner (every query is scoped by user id).
 */
final class OrderReadModel
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(int $userId, string $reference): ?array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, reference, status, total_cents, currency, created_at, paid_at FROM orders WHERE user_id = :uid AND reference = :ref',
            ['uid' => $userId, 'ref' => $reference],
        );

        return $this->hydrate($rows)[0] ?? null;
    }

    /** @return list<array<string, mixed>> newest first */
    public function listForUser(int $userId, int $limit = 50): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, reference, status, total_cents, currency, created_at, paid_at FROM orders
             WHERE user_id = :uid ORDER BY created_at DESC, id DESC LIMIT :limit',
            ['uid' => $userId, 'limit' => $limit],
            ['limit' => \Doctrine\DBAL\ParameterType::INTEGER],
        );

        return $this->hydrate($rows);
    }

    /**
     * @param list<array<string, mixed>> $orders
     *
     * @return list<array<string, mixed>>
     */
    private function hydrate(array $orders): array
    {
        if ([] === $orders) {
            return [];
        }
        $ids = array_map(static fn (array $o): int => (int) $o['id'], $orders);

        $items = $this->connection->fetchAllAssociative(
            'SELECT i.id, i.order_id, i.offer_id, i.product_name, i.unit_price_cents, i.quantity,
                    p.slug, p.cover_url, p.platform, p.region, s.name AS seller_name
             FROM order_items i
             JOIN offers o ON o.id = i.offer_id
             JOIN products p ON p.id = o.product_id
             JOIN sellers s ON s.id = o.seller_id
             WHERE i.order_id IN (:ids)
             ORDER BY i.id',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::INTEGER],
        );

        $keys = [];
        foreach ($this->connection->fetchAllAssociative(
            'SELECT k.order_item_id, k.code FROM product_keys k JOIN order_items i ON i.id = k.order_item_id
             JOIN orders o ON o.id = i.order_id
             WHERE i.order_id IN (:ids) AND o.status = \'paid\' AND k.status = \'sold\'
             ORDER BY k.id',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::INTEGER],
        ) as $key) {
            $keys[(int) $key['order_item_id']][] = $key['code'];
        }

        $byOrder = [];
        foreach ($items as $item) {
            $byOrder[(int) $item['order_id']][] = [
                'offerId' => (int) $item['offer_id'],
                'productSlug' => $item['slug'],
                'productName' => $item['product_name'],
                'coverUrl' => $item['cover_url'],
                'platform' => $item['platform'],
                'region' => $item['region'],
                'sellerName' => $item['seller_name'],
                'unitPriceCents' => (int) $item['unit_price_cents'],
                'quantity' => (int) $item['quantity'],
                'keys' => $keys[(int) $item['id']] ?? [],
            ];
        }

        return array_map(static function (array $o) use ($byOrder): array {
            $createdAt = new \DateTimeImmutable((string) $o['created_at'], new \DateTimeZone('UTC'));

            return [
                'reference' => $o['reference'],
                'status' => $o['status'],
                'totalCents' => (int) $o['total_cents'],
                'currency' => $o['currency'],
                'createdAt' => $createdAt->format(\DATE_ATOM),
                'paidAt' => null === $o['paid_at'] ? null : (new \DateTimeImmutable((string) $o['paid_at'], new \DateTimeZone('UTC')))->format(\DATE_ATOM),
                'expiresAt' => 'pending' === $o['status']
                    ? $createdAt->modify(\sprintf('+%d minutes', OrderService::RESERVATION_MINUTES))->format(\DATE_ATOM)
                    : null,
                'items' => $byOrder[(int) $o['id']] ?? [],
            ];
        }, $orders);
    }
}
