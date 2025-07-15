<?php

namespace App\Entity;

use App\Entity\Traits\Id;
use App\Entity\Traits\Timestampable;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table()
 */
class ProductPriceHistory
{
    use Id, Timestampable;

    /**
     * @var Product
     * @ORM\ManyToOne(targetEntity="App\Entity\Product", inversedBy="priceHistories")
     * @ORM\JoinColumn(referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    protected $product;

    /**
     * @ORM\Column(type="decimal", precision=20, scale=6)
     */
    protected $price;

    /**
     * @ORM\Column(type="decimal", precision=20, scale=6)
     */
    protected $regularPrice;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $discount;

    /**
     * @ORM\Column(type="decimal", precision=20, scale=6)
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
    protected $source;
    
    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;
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

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
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

    public function getUnitQuantity(): ?int
    {
        return $this->unitQuantity;
    }

    public function setUnitQuantity(int $unitQuantity): self
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
}