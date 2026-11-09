<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrderItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
#[ORM\Table(name: 'order_items')]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\ManyToOne(targetEntity: Offer::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Offer $offer;

    #[ORM\Column(length: 200)]
    private string $productName;

    #[ORM\Column]
    private int $unitPriceCents;

    #[ORM\Column]
    private int $quantity;

    /** @var Collection<int, ProductKey> */
    #[ORM\OneToMany(targetEntity: ProductKey::class, mappedBy: 'orderItem')]
    private Collection $keys;

    public function __construct(Order $order, Offer $offer, string $productName, int $unitPriceCents, int $quantity)
    {
        $this->order = $order;
        $this->offer = $offer;
        $this->productName = $productName;
        $this->unitPriceCents = $unitPriceCents;
        $this->quantity = $quantity;
        $this->keys = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function setOrder(Order $order): static
    {
        $this->order = $order;

        return $this;
    }

    public function getOffer(): Offer
    {
        return $this->offer;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function getUnitPriceCents(): int
    {
        return $this->unitPriceCents;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /** @return Collection<int, ProductKey> */
    public function getKeys(): Collection
    {
        return $this->keys;
    }

    public function addKey(ProductKey $key): static
    {
        if (!$this->keys->contains($key)) {
            $this->keys->add($key);
            $key->assignTo($this);
        }

        return $this;
    }
}
