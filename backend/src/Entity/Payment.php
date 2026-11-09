<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\PaymentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'payments')]
#[ORM\UniqueConstraint(name: 'uniq_payment_idempotency_key', columns: ['idempotency_key'])]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\Column(length: 32)]
    private string $provider;

    #[ORM\Column(length: 64)]
    private string $idempotencyKey;

    #[ORM\Column(type: 'string', length: 16, enumType: PaymentStatus::class, options: ['default' => 'pending'])]
    private PaymentStatus $status = PaymentStatus::Pending;

    #[ORM\Column]
    private int $amountCents;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $providerReference = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $failureReason = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Order $order, string $provider, string $idempotencyKey, int $amountCents)
    {
        $this->order = $order;
        $this->provider = $provider;
        $this->idempotencyKey = $idempotencyKey;
        $this->amountCents = $amountCents;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function getAmountCents(): int
    {
        return $this->amountCents;
    }

    public function getProviderReference(): ?string
    {
        return $this->providerReference;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function markSucceeded(string $providerReference): void
    {
        $this->status = PaymentStatus::Succeeded;
        $this->providerReference = $providerReference;
    }

    public function markFailed(string $reason): void
    {
        $this->status = PaymentStatus::Failed;
        $this->failureReason = $reason;
    }
}
