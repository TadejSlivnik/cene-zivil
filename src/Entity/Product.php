<?php

namespace App\Entity;

use App\Entity\Traits\GedmoSoftDeletable;
use App\Entity\Traits\Id;
use App\Entity\Traits\Timestampable;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ProductRepository")
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="unique_product_source", columns={"product_id", "source"})})
 * @Gedmo\SoftDeleteable()
 */
class Product
{
    use Id, Timestampable, GedmoSoftDeletable;

    const SOURCE_HOFER = 'hofer';
    const SOURCE_LIDL = 'lidl';
    const SOURCE_MERCATOR = 'mercator';
    const SOURCE_SPAR = 'spar';
    const SOURCE_DM = 'dm';
    const SOURCE_TUS = 'tus';
    const SOURCES = [
        self::SOURCE_DM => 'dm',
        self::SOURCE_HOFER => 'HOFER',
        self::SOURCE_LIDL => 'Lidl',
        self::SOURCE_MERCATOR => 'Mercator',
        self::SOURCE_SPAR => 'SPAR',
        self::SOURCE_TUS => 'Tuš',
    ];

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $productId;

    /**
     * @ORM\Column(type="string", length=1024, nullable=true)
     */
    protected $ean;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $title;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    protected $price;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    protected $regularPrice;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $discount;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    protected $unitPrice;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $unit;

    /**
     * @ORM\Column(type="integer")
     */
    protected $unitQuantity;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $url;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $source;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $promotionEndsDate;

    public function __toString(): string
    {
        return $this->title;
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): self
    {
        $this->productId = $productId;
        return $this;
    }

    public function getEanArray(): array
    {
        if (!$this->ean) {
            return [];
        }
        return array_filter(explode(',', $this->ean));
    }

    public function getEan(): ?string
    {
        return $this->ean;
    }

    public function setEan(?string $ean): self
    {
        $this->ean = $ean;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }
    
    public function getPrice(): ?float
    {
        return $this->price;
    }
    
    public function setPrice(float $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        if (!array_key_exists($source, self::SOURCES)) {
            throw new \InvalidArgumentException("Invalid source: $source. Valid sources are: " . implode(', ', array_keys(self::SOURCES)));
        }
        $this->source = $source;
        return $this;
    }

    public function getRegularPrice(): ?float
    {
        return $this->regularPrice;
    }

    public function setRegularPrice(float $regularPrice): self
    {
        $this->regularPrice = $regularPrice;
        return $this;
    }

    public function getUnitPrice(): ?float
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(float $unitPrice): self
    {
        $this->unitPrice = $unitPrice;
        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(string $unit): self
    {
        $this->unit = $unit;
        return $this;
    }

    public function getUnitQuantity(): ?string
    {
        return $this->unitQuantity;
    }

    public function setUnitQuantity(string $unitQuantity): self
    {
        $this->unitQuantity = $unitQuantity;
        return $this;
    }
    
    public function getDiscount(): ?int
    {
        return $this->discount;
    }
    public function setDiscount(?int $discount): self
    {
        $this->discount = $discount;
        return $this;
    }

    public function getTrgovina(): ?string
    {
        return self::SOURCES[$this->source] ?? null;
    }

    public function isUpToDate(): bool
    {
        if ($this->getUpdatedAt() === null) {
            return false;
        }
        return $this->getUpdatedAt()->diff(new \DateTime())->days < 7;
    }

    public function updatedToday(): bool
    {
        if ($this->getUpdatedAt() === null) {
            return false;
        }
        return $this->getUpdatedAt()->format('Y-m-d') === (new \DateTime())->format('Y-m-d');
    }

    public function getPromotionEndsDate(): ?\DateTimeInterface
    {
        return $this->promotionEndsDate;
    }

    public function setPromotionEndsDate(?\DateTimeInterface $promotionEndsDate): self
    {
        $this->promotionEndsDate = $promotionEndsDate;
        return $this;
    }
}