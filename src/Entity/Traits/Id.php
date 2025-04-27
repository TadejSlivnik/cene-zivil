<?php

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;

trait Id
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
