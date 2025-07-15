<?php

namespace App\Repository;

use App\Entity\SearchTermCount;
use Doctrine\ORM\EntityRepository;

class SearchTermCountRepository extends EntityRepository
{
    public function addSearchTerms(array $terms): void
    {
        foreach ($terms as $term) {

            $searchTerm = $this->find($term);
            if (!$searchTerm) {
                $searchTerm = new SearchTermCount();
                $searchTerm->setSearchTerm($term);
                $this->_em->persist($searchTerm);
            }

            $searchTerm->incrementCount();
        }

        $this->_em->flush();
    }
}
