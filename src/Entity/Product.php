<?php

namespace App\Entity;

use App\Entity\Traits\Timestampable;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="unique_product_source", columns={"product_id", "source"})})
 */
class Product
{
    use Timestampable;

    const SOURCE_HOFER = 'hofer';
    const SOURCE_LIDL = 'lidl';
    const SOURCE_MERCATOR = 'mercator';
    const SOURCE_SPAR = 'spar';
    const SOURCE_DM = 'dm';
    const SOURCES = [
        self::SOURCE_HOFER,
        self::SOURCE_LIDL,
        self::SOURCE_MERCATOR,
        self::SOURCE_SPAR,
        self::SOURCE_DM,
    ];

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $productId;

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
     * @ORM\Column(type="string", length=255)
     */
    protected $url;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $source;

    public function __toString(): string
    {
        return $this->title;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function setUrl(string $url): self
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
        if (!in_array($source, self::SOURCES)) {
            throw new \InvalidArgumentException("Invalid source: $source. Valid sources are: " . implode(', ', self::SOURCES));
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

    public function getTrgovina(): ?string
    {
        $trgovina = $this->source;
        switch ($trgovina) {
            case self::SOURCE_LIDL:
            case self::SOURCE_MERCATOR:
                $trgovina = ucfirst($trgovina);
                break;
            case self::SOURCE_HOFER:
            case self::SOURCE_SPAR:
                $trgovina = strtoupper($trgovina);
                break;
            default:
                break;
        }
        return $trgovina;
    }
}