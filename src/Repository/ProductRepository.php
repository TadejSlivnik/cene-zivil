<?php

namespace App\Repository;

use Doctrine\ORM\EntityRepository;

class ProductRepository extends EntityRepository
{
    public function findByTerms(array $terms, bool $discountedOnly = false, array $sources = []): array
    {
        $qb = $this->createQueryBuilder('p')
            ->addOrderBy('p.regularPrice', 'ASC')
            ->addOrderBy('p.price', 'ASC')
            ->addOrderBy('p.unitPrice', 'ASC')
            ->setMaxResults(500);

        if ($discountedOnly) {
            $qb->andWhere('p.discount IS NOT NULL')
                ->andWhere('p.discount > 0')
                ->orderBy('p.discount', 'DESC')
                ->addOrderBy('p.regularPrice', 'DESC')
                ->addOrderBy('p.unitPrice', 'DESC')
                ->addOrderBy('p.price', 'DESC');
        }

        if (!empty($sources)) {
            $qb->andWhere('p.source IN (:sources)')
                ->setParameter('sources', $sources);
        }

        foreach ($terms as $k => $term) {
            $qb->andWhere("p.title LIKE :termA$k OR p.productId = :termB$k")
                ->setParameter("termA$k", "%$term%")
                ->setParameter("termB$k", "$term");
        }

        return $qb->getQuery()->getResult();
    }
}
