<?php

namespace App\Repository;

use Doctrine\ORM\EntityRepository;

class CommandQueueImageRepository extends EntityRepository
{
    public function getNextInQueue(?int $limit = 5): array
    {
        $qb = $this->createQueryBuilder('q')
            ->andWhere('q.completedAt IS NULL')
            ->andWhere('q.tries < 4')
            ->addOrderBy('q.tries', 'ASC')
            ->addOrderBy('q.id', 'ASC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }
}
