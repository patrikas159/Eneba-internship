<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductKeyRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductKeyRepository::class)]
#[ORM\Table(name: 'product_keys')]
#[ORM\Index(name: 'idx_key_offer_status', columns: ['offer_id', 'status'])]
class ProductKey
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Offer::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Offer $offer;

    #[ORM\Column(length: 64, unique: true)]
    private string $code;

    #[ORM\Column(type: 'string', length: 16, enumType: KeyStatus::class, options: ['default' => 'available'])]
    private KeyStatus $status = KeyStatus::Available;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $reservedUntil = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Offer $offer, string $code)
    {
        $this->offer = $offer;
        $this->code = $code;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOffer(): Offer
    {
        return $this->offer;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getStatus(): KeyStatus
    {
        return $this->status;
    }

    public function getReservedUntil(): ?\DateTimeImmutable
    {
        return $this->reservedUntil;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function reserve(\DateTimeImmutable $until): void
    {
        $this->status = KeyStatus::Reserved;
        $this->reservedUntil = $until;
    }

    public function release(): void
    {
        $this->status = KeyStatus::Available;
        $this->reservedUntil = null;
    }

    public function markSold(): void
    {
        $this->status = KeyStatus::Sold;
        $this->reservedUntil = null;
    }
}
