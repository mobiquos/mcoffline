<?php

namespace App\Repository;

use App\Entity\Contingency;
use App\Entity\Quote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Quote>
 */
class QuoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quote::class);
    }

    public function findByPublicId(int $id): ?Quote
    {
        $result = $this->createQueryBuilder('e')
            ->join('e.contingency', 'contingency')
            ->leftJoin('e.sale', 'sale')
            ->where('e.publicId = :publicId')
            ->andWhere('sale.id is null and DATE(e.quoteDate) = :today and contingency.endedAt is null')
            ->setParameter('publicId', $id)
            ->setParameter('today', (new \DateTime())->format("Y-m-d"))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        return $result;
    }

    public function findByRut(string $rut): array
    {
        return $this->createQueryBuilder('e')
            ->where('LOWER(e.rut) = :rut')
            ->setParameter('rut', strtolower(str_replace(['.', '-'], '', $rut)))
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the maximum publicId for quotes on a specific date within a contingency
     *
     * @param \DateTime $date
     * @param Contingency $contingency
     * @return int
     */
    public function findMaxPublicIdForDate(\DateTime $date, Contingency $contingency): int
    {
        $result = $this->createQueryBuilder('q')
            ->select('MAX(q.publicId)')
            ->where('DATE(q.quoteDate) = :date')
            ->andWhere('q.contingency = :contingency')
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('contingency', $contingency)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (int)$result : 0;
    }

    //    /**
    //     * @return Quote[] Returns an array of Quote objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Quote
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
