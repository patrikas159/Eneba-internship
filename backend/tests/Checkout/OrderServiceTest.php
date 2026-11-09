<?php

declare(strict_types=1);

namespace App\Tests\Checkout;

use App\Checkout\CheckoutException;
use App\Checkout\OrderService;
use App\Checkout\SimulatedPaymentProvider;
use App\Entity\Offer;
use App\Entity\Product;
use App\Entity\ProductKey;
use App\Entity\Seller;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests against the real PostgreSQL test database. Each test runs inside a
 * transaction that DAMA DoctrineTestBundle rolls back afterwards, so fixtures never leak.
 */
final class OrderServiceTest extends KernelTestCase
{
    private const PRICE = 1999;
    private const KEYS = 3;
    private const GOOD_CARD = ['cardNumber' => '4242 4242 4242 4242'];
    private const DECLINED_CARD = ['cardNumber' => SimulatedPaymentProvider::DECLINED_TEST_CARD];

    private OrderService $orders;
    private Connection $db;
    private User $user;
    private Offer $offer;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->orders = $container->get(OrderService::class);
        $this->db = $container->get(Connection::class);
        $em = $container->get(EntityManagerInterface::class);

        $suffix = bin2hex(random_bytes(4));
        $this->user = new User("buyer-$suffix@example.com", 'x', 'Buyer');
        $seller = new Seller('Test Seller', "test-seller-$suffix");
        $product = new Product("test-game-$suffix", 'Test Game', 'A game.', 'Steam', 'Global');
        $this->offer = new Offer($product, $seller, self::PRICE);

        foreach ([$this->user, $seller, $product, $this->offer] as $entity) {
            $em->persist($entity);
        }
        for ($i = 0; $i < self::KEYS; ++$i) {
            $em->persist(new ProductKey($this->offer, "TEST-$suffix-$i"));
        }
        $em->flush();
    }

    public function testPlaceOrderReservesKeysAndComputesTotalFromOfferPrice(): void
    {
        $ref = $this->orders->placeOrder($this->user, [$this->offer->getId() => 2]);

        $order = $this->order($ref);
        self::assertSame('pending', $order['status']);
        self::assertSame(2 * self::PRICE, (int) $order['total_cents']);
        self::assertSame(['available' => 1, 'reserved' => 2], $this->keyStatuses());
    }

    public function testPlaceOrderFailsWhenNotEnoughKeys(): void
    {
        $this->assertCheckoutError('out_of_stock', fn () => $this->orders->placeOrder($this->user, [$this->offer->getId() => self::KEYS + 1]));
        self::assertSame(['available' => self::KEYS], $this->keyStatuses());
    }

    public function testInactiveOfferIsUnavailable(): void
    {
        $this->offer->setActive(false);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->assertCheckoutError('offer_unavailable', fn () => $this->orders->placeOrder($this->user, [$this->offer->getId() => 1]));
    }

    public function testSuccessfulPaymentMarksOrderPaidAndKeysSold(): void
    {
        $ref = $this->orders->placeOrder($this->user, [$this->offer->getId() => 2]);

        $result = $this->orders->pay($this->user, $ref, $this->key(), self::GOOD_CARD);

        self::assertTrue($result['succeeded']);
        self::assertFalse($result['replayed']);
        self::assertSame('paid', $this->order($ref)['status']);
        self::assertSame(['available' => 1, 'sold' => 2], $this->keyStatuses());
    }

    public function testReplayingIdempotencyKeyDoesNotChargeTwice(): void
    {
        $ref = $this->orders->placeOrder($this->user, [$this->offer->getId() => 1]);
        $key = $this->key();

        $first = $this->orders->pay($this->user, $ref, $key, self::GOOD_CARD);
        $second = $this->orders->pay($this->user, $ref, $key, self::GOOD_CARD);

        self::assertFalse($first['replayed']);
        self::assertTrue($second['replayed']);
        self::assertTrue($second['succeeded']);
        self::assertSame(1, $this->paymentCount($ref));
    }

    public function testIdempotencyKeyCannotBeReusedForAnotherOrder(): void
    {
        $first = $this->orders->placeOrder($this->user, [$this->offer->getId() => 1]);
        $second = $this->orders->placeOrder($this->user, [$this->offer->getId() => 1]);
        $key = $this->key();
        $this->orders->pay($this->user, $first, $key, self::GOOD_CARD);

        $this->assertCheckoutError('idempotency_key_reused', fn () => $this->orders->pay($this->user, $second, $key, self::GOOD_CARD));
    }

    public function testPayingAPaidOrderWithNewKeyIsRejected(): void
    {
        $ref = $this->orders->placeOrder($this->user, [$this->offer->getId() => 1]);
        $this->orders->pay($this->user, $ref, $this->key(), self::GOOD_CARD);

        $this->assertCheckoutError('order_already_paid', fn () => $this->orders->pay($this->user, $ref, $this->key(), self::GOOD_CARD));
    }

    public function testDeclinedCardKeepsOrderPendingAndAllowsRetry(): void
    {
        $ref = $this->orders->placeOrder($this->user, [$this->offer->getId() => 1]);

        $declined = $this->orders->pay($this->user, $ref, $this->key(), self::DECLINED_CARD);

        self::assertFalse($declined['succeeded']);
        self::assertNotNull($declined['failureReason']);
        self::assertSame('pending', $this->order($ref)['status']);
        self::assertSame(['available' => 2, 'reserved' => 1], $this->keyStatuses());

        $retry = $this->orders->pay($this->user, $ref, $this->key(), self::GOOD_CARD);
        self::assertTrue($retry['succeeded']);
        self::assertSame('paid', $this->order($ref)['status']);
    }

    public function testOtherUsersCannotPayTheOrder(): void
    {
        $ref = $this->orders->placeOrder($this->user, [$this->offer->getId() => 1]);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $stranger = new User('stranger-'.bin2hex(random_bytes(4)).'@example.com', 'x', 'Stranger');
        $em->persist($stranger);
        $em->flush();

        $this->assertCheckoutError('not_found', fn () => $this->orders->pay($stranger, $ref, $this->key(), self::GOOD_CARD));
    }

    public function testExpiredReservationCancelsOrderAndFreesKeys(): void
    {
        $ref = $this->orders->placeOrder($this->user, [$this->offer->getId() => 2]);
        $this->ageOrder($ref, OrderService::RESERVATION_MINUTES + 1);

        // expireStale() runs first and cancels the order, so pay() sees a cancelled order.
        $this->assertCheckoutError('order_not_payable', fn () => $this->orders->pay($this->user, $ref, $this->key(), self::GOOD_CARD));
        self::assertSame('cancelled', $this->order($ref)['status']);
        self::assertSame(['available' => self::KEYS], $this->keyStatuses());
        self::assertSame(0, $this->paymentCount($ref));
    }

    public function testExpiredKeysCanBeBoughtByTheNextBuyer(): void
    {
        $stale = $this->orders->placeOrder($this->user, [$this->offer->getId() => self::KEYS]);
        // Only the key reservation lapsed; the order itself is not old enough for expireStale().
        $this->db->executeStatement("UPDATE product_keys SET reserved_until = now() - interval '1 minute' WHERE offer_id = :o", ['o' => $this->offer->getId()]);

        $fresh = $this->orders->placeOrder($this->user, [$this->offer->getId() => self::KEYS]);
        self::assertNotSame($stale, $fresh);

        $this->assertCheckoutError('reservation_expired', fn () => $this->orders->pay($this->user, $stale, $this->key(), self::GOOD_CARD));
    }

    public function testExpireStaleOnlyTouchesOldPendingOrders(): void
    {
        $old = $this->orders->placeOrder($this->user, [$this->offer->getId() => 1]);
        $recent = $this->orders->placeOrder($this->user, [$this->offer->getId() => 1]);
        $this->ageOrder($old, OrderService::RESERVATION_MINUTES + 1);

        self::assertSame(1, $this->orders->expireStale((int) $this->user->getId()));
        self::assertSame('cancelled', $this->order($old)['status']);
        self::assertSame('pending', $this->order($recent)['status']);
        self::assertSame(['available' => 2, 'reserved' => 1], $this->keyStatuses());
    }

    private function assertCheckoutError(string $code, callable $action): void
    {
        try {
            $action();
        } catch (CheckoutException $e) {
            self::assertSame($code, $e->errorCode, $e->getMessage());

            return;
        }
        self::fail("Expected CheckoutException '$code'");
    }

    /**
     * @return array<string, mixed>
     */
    private function order(string $ref): array
    {
        $row = $this->db->fetchAssociative('SELECT * FROM orders WHERE reference = :r', ['r' => $ref]);
        self::assertIsArray($row);

        return $row;
    }

    /**
     * @return array<string, int> key status => count, for this test's offer
     */
    private function keyStatuses(): array
    {
        $rows = $this->db->fetchAllKeyValue(
            'SELECT status, count(*) FROM product_keys WHERE offer_id = :o GROUP BY status ORDER BY status',
            ['o' => $this->offer->getId()],
        );

        return array_map('intval', $rows);
    }

    private function paymentCount(string $ref): int
    {
        return (int) $this->db->fetchOne(
            'SELECT count(*) FROM payments p JOIN orders o ON o.id = p.order_id WHERE o.reference = :r',
            ['r' => $ref],
        );
    }

    private function ageOrder(string $ref, int $minutes): void
    {
        $this->db->executeStatement(
            "UPDATE orders SET created_at = created_at - make_interval(mins => :m) WHERE reference = :r",
            ['m' => $minutes, 'r' => $ref],
        );
        $this->db->executeStatement(
            "UPDATE product_keys SET reserved_until = reserved_until - make_interval(mins => :m) WHERE status = 'reserved' AND offer_id = :o",
            ['m' => $minutes, 'o' => $this->offer->getId()],
        );
    }

    private function key(): string
    {
        return 'test-'.bin2hex(random_bytes(8));
    }
}
