<?php

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\Contingency;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 *
 * @method Payment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Payment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Payment[]    findAll()
 * @method Payment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function getContingencyReport(Contingency $contingency): array
    {
        $qb = $this->createQueryBuilder('p');
        $qb->select('COUNT(p.id) as paymentsCount', 'SUM(p.amount) as totalAmount')
            ->where('p.contingency = :contingency')
            ->setParameter('contingency', $contingency);

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * Get the next correlative ID for payments of the day
     *
     * @param \DateTimeInterface $date The date to get the correlative for
     * @return int The next correlative ID
     */
    public function getNextCorrelativeId(\DateTimeInterface $date): int
    {
        $qb = $this->createQueryBuilder('p');
        $qb->select('COUNT(p.id)')
            ->where('DATE(p.createdAt) = :date')
            ->setParameter('date', $date->format('Y-m-d'));

        $count = (int) $qb->getQuery()->getSingleScalarResult();
        
        // Return the next correlative (count + 1)
        return $count + 1;
    }

//    /**
//     * @return Payment[] Returns an array of Payment objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Payment
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
