<?php

namespace App\Entity;

use App\Entity\Traits\Id;
use App\Entity\Traits\Timestampable;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table()
 */
class CommandQueueImage
{
    use Id, Timestampable;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Product")
     * @ORM\JoinColumn(referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    protected $product;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $imageUrl;

    /**
     * @ORM\Column(type="integer", options={"default":0})
     */
    protected $tries = 0;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $lastError;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $completedAt;

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function getImageUrl(): string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(string $imageUrl): self
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    public function getTries(): int
    {
        return $this->tries;
    }

    public function setTries(int $tries): self
    {
        $this->tries = $tries;
        return $this;
    }

    public function incrementTries(): self
    {
        $this->tries++;
        return $this;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): self
    {
        $this->lastError = $lastError;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeInterface $completedAt): self
    {
        $this->completedAt = $completedAt;
        return $this;
    }
}
