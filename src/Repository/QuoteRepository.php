<?php

namespace App\Repository;

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

    public function findByRut(string $rut): array
    {
        return $this->createQueryBuilder('e')
            ->where('LOWER(e.rut) = :rut')
            ->setParameter('rut', strtolower(str_replace(['.', '-'], '', $rut)))
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();
    }

    public function findWithClients($contingency): array
    {
        return $this->createQueryBuilder('q')
            ->select("q.id as id, q.quoteDate as quoteDate, q.rut as rut, q.amount as amount, CONCAT(c.firstLastName, ' ', c.secondLastName, ' ', c.name) as clientName")
            ->leftJoin('App\Entity\Client', 'c', 'WITH', 'q.rut = c.rut')
            ->andWhere('q.contingency = :contingency')
            ->setParameter('contingency', $contingency)
            ->orderBy('q.quoteDate', 'DESC')
            ->getQuery()
            ->getScalarResult();
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
