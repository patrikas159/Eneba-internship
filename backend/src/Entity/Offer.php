<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\OfferRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OfferRepository::class)]
#[ORM\Table(name: 'offers')]
#[ORM\UniqueConstraint(name: 'uniq_offer_product_seller', columns: ['product_id', 'seller_id'])]
#[ORM\Index(name: 'idx_offer_product_active', columns: ['product_id', 'active'])]
class Offer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'offers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\ManyToOne(targetEntity: Seller::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Seller $seller;

    #[ORM\Column]
    private int $priceCents;

    #[ORM\Column(nullable: true)]
    private ?int $originalPriceCents = null;

    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    private string $currency = 'EUR';

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Product $product, Seller $seller, int $priceCents)
    {
        $this->product = $product;
        $this->seller = $seller;
        $this->priceCents = $priceCents;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getSeller(): Seller
    {
        return $this->seller;
    }

    public function getPriceCents(): int
    {
        return $this->priceCents;
    }

    public function setPriceCents(int $priceCents): static
    {
        $this->priceCents = $priceCents;

        return $this;
    }

    public function getOriginalPriceCents(): ?int
    {
        return $this->originalPriceCents;
    }

    public function setOriginalPriceCents(?int $originalPriceCents): static
    {
        $this->originalPriceCents = $originalPriceCents;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
