<?php

namespace App\Repository;

use App\Entity\SyncProgress;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SyncProgress>
 */
class SyncProgressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SyncProgress::class);
    }

    public function findBySyncEventAndEntityType($syncEvent, $entityType): ?SyncProgress
    {
        return $this->createQueryBuilder('sp')
            ->andWhere('sp.syncEvent = :syncEvent')
            ->andWhere('sp.entityType = :entityType')
            ->setParameter('syncEvent', $syncEvent)
            ->setParameter('entityType', $entityType)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findIncompleteBySyncEvent($syncEvent)
    {
        return $this->createQueryBuilder('sp')
            ->andWhere('sp.syncEvent = :syncEvent')
            ->andWhere('sp.completed = false')
            ->setParameter('syncEvent', $syncEvent)
            ->getQuery()
            ->getResult()
        ;
    }
}