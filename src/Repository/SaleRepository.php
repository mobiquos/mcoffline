<?php

namespace App\Repository;

use App\Entity\Contingency;
use App\Entity\Sale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Sale>
 */
class SaleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sale::class);
    }

    public function getContingencyReport(Contingency $contingency): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id) as salesCount, SUM(q.totalAmount) as totalAmount')
            ->join('s.quote', 'q')
            ->where('q.locationCode = :locationCode')
            ->andWhere('q.quoteDate >= :startedAt')
            ->setParameter('locationCode', $contingency->getLocation()->getCode())
            ->setParameter('startedAt', $contingency->getStartedAt());

        if ($contingency->getEndedAt()) {
            $qb->andWhere('q.quoteDate <= :endedAt')
                ->setParameter('endedAt', $contingency->getEndedAt());
        }

        return $qb->getQuery()->getSingleResult();
    }
}