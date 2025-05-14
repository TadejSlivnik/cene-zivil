<?php

namespace App\Repository;

use Doctrine\ORM\EntityRepository;

class ProductRepository extends EntityRepository
{
    public function findByTerms(array $terms, bool $discountedOnly = false, array $sources = [], array $pins = []): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.deletedAt IS NULL')
            ->setMaxResults(500);

        $or = $qb->expr()->orX();
        $and = $qb->expr()->andX();

        if ($pins) {
            $or->add($qb->expr()->in('p.id', ':pins'));
            $qb->setParameter('pins', $pins);

            $caseExpr = 'CASE';
            foreach ($pins as $i => $id) {
                $id = (int)$id; // Ensure safety
                $caseExpr .= " WHEN p.id = $id THEN $i";
            }
            $caseExpr .= ' ELSE 1000 END';

            // Select hidden pin_order and order by it
            $qb->addSelect("($caseExpr) AS HIDDEN pin_order");
            $qb->addOrderBy('pin_order', 'ASC');
        }

        if ($discountedOnly) {
            $and->add($qb->expr()->gt('p.discount', 0));
            $and->add($qb->expr()->isNotNull('p.discount'));

            $qb->addOrderBy('p.discount', 'DESC')
                ->addOrderBy('p.regularPrice', 'DESC')
                ->addOrderBy('p.price', 'ASC')
                ->addOrderBy('p.unitPrice', 'ASC');
        } else {
            $qb->addOrderBy('p.regularPrice', 'ASC')
                ->addOrderBy('p.price', 'ASC')
                ->addOrderBy('p.unitPrice', 'ASC');
        }

        if (!empty($sources)) {
            $and->add($qb->expr()->in('p.source', ':sources'));
            $qb->setParameter('sources', $sources);
        }

        foreach ($terms as $k => $term) {
            $and->add($qb->expr()->orX(
                $qb->expr()->like('p.title', ":termA$k"),
                $qb->expr()->eq('p.productId', ":termB$k")
            ));
            $qb->setParameter("termA$k", "%$term%")->setParameter("termB$k", "$term");
        }

        $or->add($and);
        $qb->andWhere($or);

        return $qb->getQuery()->getResult();
    }
}
