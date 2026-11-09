<?php

declare(strict_types=1);

namespace App\Controller;

use App\Checkout\CheckoutException;
use App\Checkout\OrderReadModel;
use App\Checkout\OrderService;
use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Checkout + order history. All routes sit under `^/api/orders`, which security.yaml
 * restricts to signed-in users; orders of other users are reported as 404.
 */
final class OrderController
{
    private const REFERENCE_PATTERN = '[A-Z0-9-]{1,20}';

    public function __construct(
        private readonly OrderService $orders,
        private readonly OrderReadModel $readModel,
    ) {
    }

    #[Route('/api/orders', name: 'api_orders_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $body = $request->toArray();
        } catch (\Throwable) {
            $body = [];
        }

        $lines = [];
        $items = $body['items'] ?? null;
        if (!\is_array($items) || !array_is_list($items) || [] === $items || \count($items) > OrderService::MAX_LINES) {
            return $this->invalid(\sprintf('`items` must be a list of 1-%d cart lines.', OrderService::MAX_LINES));
        }
        foreach ($items as $i => $line) {
            $offerId = \is_array($line) ? ($line['offerId'] ?? null) : null;
            $quantity = \is_array($line) ? ($line['quantity'] ?? null) : null;
            if (!\is_int($offerId) || $offerId < 1 || !\is_int($quantity) || $quantity < 1) {
                return $this->invalid(\sprintf('items[%d] needs a positive integer `offerId` and `quantity`.', $i));
            }
            $lines[$offerId] = ($lines[$offerId] ?? 0) + $quantity;
            if ($lines[$offerId] > OrderService::MAX_QUANTITY) {
                return $this->invalid(\sprintf('At most %d keys per offer.', OrderService::MAX_QUANTITY));
            }
        }

        try {
            $reference = $this->orders->placeOrder($user, $lines);
        } catch (CheckoutException $e) {
            return new JsonResponse($e->toArray(), $e->httpStatus);
        }

        return new JsonResponse(['order' => $this->readModel->find((int) $user->getId(), $reference)], 201);
    }

    #[Route('/api/orders/{reference}/pay', name: 'api_orders_pay', methods: ['POST'], requirements: ['reference' => self::REFERENCE_PATTERN])]
    public function pay(string $reference, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $key = (string) $request->headers->get('Idempotency-Key', '');
        if (1 !== preg_match('/^[A-Za-z0-9_-]{8,64}$/', $key)) {
            return new JsonResponse(['error' => 'invalid_idempotency_key', 'message' => 'Send an `Idempotency-Key` header of 8-64 characters [A-Za-z0-9_-].'], 400);
        }
        try {
            $body = $request->toArray();
        } catch (\Throwable) {
            $body = [];
        }
        $options = ['cardNumber' => \is_scalar($body['cardNumber'] ?? null) ? (string) $body['cardNumber'] : ''];

        try {
            $result = $this->orders->pay($user, $reference, $key, $options);
        } catch (CheckoutException $e) {
            return new JsonResponse($e->toArray(), $e->httpStatus);
        }

        $order = $this->readModel->find((int) $user->getId(), $reference);
        $headers = $result['replayed'] ? ['Idempotent-Replayed' => 'true'] : [];
        if (!$result['succeeded']) {
            return new JsonResponse([
                'error' => 'payment_declined',
                'message' => $result['failureReason'] ?? 'The payment was declined.',
                'order' => $order,
            ], 402, $headers);
        }

        return new JsonResponse(['order' => $order], 200, $headers);
    }

    #[Route('/api/orders', name: 'api_orders_list', methods: ['GET'])]
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        $this->orders->expireStale((int) $user->getId());

        return new JsonResponse(['items' => $this->readModel->listForUser((int) $user->getId())]);
    }

    #[Route('/api/orders/{reference}', name: 'api_orders_show', methods: ['GET'], requirements: ['reference' => self::REFERENCE_PATTERN])]
    public function show(string $reference, #[CurrentUser] User $user): JsonResponse
    {
        $this->orders->expireStale((int) $user->getId());
        $order = $this->readModel->find((int) $user->getId(), $reference);
        if (null === $order) {
            return new JsonResponse(OrderService::orderNotFound()->toArray(), 404);
        }

        return new JsonResponse(['order' => $order]);
    }

    private function invalid(string $message): JsonResponse
    {
        return new JsonResponse(['error' => 'validation_failed', 'message' => $message], 422);
    }
}
