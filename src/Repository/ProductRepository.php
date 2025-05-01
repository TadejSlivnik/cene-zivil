<?php

namespace App\Repository;

use Doctrine\ORM\EntityRepository;

class ProductRepository extends EntityRepository
{
    public function findByTerms(array $terms)
    {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.discount', 'DESC')
            ->addOrderBy('p.regularPrice', 'ASC')
            ->addOrderBy('p.price', 'ASC')
            ->addOrderBy('p.unitPrice', 'ASC')
            ->setMaxResults(1000);

        foreach ($terms as $k => $term) {
            $qb->andWhere("p.title LIKE :termA$k OR p.productId = :termB$k")
                ->setParameter("termA$k", "%$term%")
                ->setParameter("termB$k", "$term");
        }

        return $qb->getQuery()->getResult();
    }

    public function findMostDiscountedProducts()
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.discount IS NOT NULL')
            ->andWhere('p.discount > 0')
            ->orderBy('p.discount', 'DESC')
            ->addOrderBy('p.regularPrice', 'ASC')
            ->addOrderBy('p.price', 'ASC')
            ->addOrderBy('p.unitPrice', 'ASC')
            ->setMaxResults(1000)
            ->getQuery()
            ->getResult();
    }
}
