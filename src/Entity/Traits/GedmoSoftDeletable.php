<?php

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;

trait GedmoSoftDeletable
{
    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected ?\DateTime $deletedAt = null;

    public function getDeletedAt(): ?\DateTime
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTime $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }
}
