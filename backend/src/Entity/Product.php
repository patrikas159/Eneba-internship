<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'products')]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160, unique: true)]
    private string $slug;

    #[ORM\Column(length: 200)]
    private string $name;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $coverUrl = null;

    #[ORM\Column(length: 40)]
    private string $platform;

    #[ORM\Column(length: 40)]
    private string $region;

    #[ORM\Column(type: 'json')]
    private array $genres = [];

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $releaseDate = null;

    #[ORM\Column(nullable: true, unique: true)]
    private ?int $igdbId = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Offer> */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: Offer::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $offers;

    public function __construct(
        string $slug,
        string $name,
        string $description,
        string $platform,
        string $region,
    ) {
        $this->slug = $slug;
        $this->name = $name;
        $this->description = $description;
        $this->platform = $platform;
        $this->region = $region;
        $this->createdAt = new \DateTimeImmutable();
        $this->offers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCoverUrl(): ?string
    {
        return $this->coverUrl;
    }

    public function setCoverUrl(?string $coverUrl): static
    {
        $this->coverUrl = $coverUrl;

        return $this;
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function getRegion(): string
    {
        return $this->region;
    }

    /** @return list<string> */
    public function getGenres(): array
    {
        return $this->genres;
    }

    /** @param list<string> $genres */
    public function setGenres(array $genres): static
    {
        $this->genres = $genres;

        return $this;
    }

    public function getReleaseDate(): ?\DateTimeImmutable
    {
        return $this->releaseDate;
    }

    public function setReleaseDate(?\DateTimeImmutable $releaseDate): static
    {
        $this->releaseDate = $releaseDate;

        return $this;
    }

    public function getIgdbId(): ?int
    {
        return $this->igdbId;
    }

    public function setIgdbId(?int $igdbId): static
    {
        $this->igdbId = $igdbId;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Offer> */
    public function getOffers(): Collection
    {
        return $this->offers;
    }
}
