<?php

namespace App\Repository;

use Doctrine\ORM\EntityRepository;

class ProductRepository extends EntityRepository
{
    public function findMostDiscountedProducts(int $limit = 1000)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.discount IS NOT NULL')
            ->andWhere('p.discount > 0')
            ->orderBy('p.discount', 'DESC')
            ->addOrderBy('p.regularPrice', 'ASC')
            ->addOrderBy('p.price', 'ASC')
            ->addOrderBy('p.unitPrice', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
