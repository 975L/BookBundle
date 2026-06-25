<?php

namespace c975L\BookBundle\Repository;

use c975L\BookBundle\Entity\Media;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Media::class);
    }

/*     public function findByEntity(string $entityType, int $entityId): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.entityType = :entityType')
            ->andWhere('m.entityId = :entityId')
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId)
            ->orderBy('m.category', 'ASC')
            ->addOrderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByEntityAndCategory(string $entityType, int $entityId, string $category): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.entityType = :entityType')
            ->andWhere('m.entityId = :entityId')
            ->andWhere('m.category = :category')
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId)
            ->setParameter('category', $category)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
 */
}
