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
            ->join('s.contingency', 'c')
            ->where('c.id = :contingencyId')
            ->setParameter('contingencyId', $contingency->getId())
        ;

        return $qb->getQuery()->getSingleResult();
    }
}
