<?php

namespace App\Entity;

use App\Entity\Traits\Timestampable;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\SearchTermCountRepository")
 * @ORM\Table()
 */
class SearchTermCount
{
    use Timestampable;

    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=255)
     */
    protected $searchTerm;

    /**
     * @ORM\Column(type="integer", options={"default":0})
     */
    protected $count = 0;

    public function getSearchTerm(): ?string
    {
        return $this->searchTerm;
    }

    public function setSearchTerm(string $searchTerm): self
    {
        $this->searchTerm = $searchTerm;
        return $this;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function setCount(int $count): self
    {
        $this->count = $count;
        return $this;
    }

    public function incrementCount(): self
    {
        $this->count++;
        return $this;
    }
}
