<?php

namespace c975L\BookBundle\Repository;

use c975L\BookBundle\Entity\Media;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository<\c975L\BookBundle\Entity\Media>
 */
class MediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Media::class);
    }

    // Every document this bundle serves as a PDF, whatever the entity holding it - read by BookPdfDocumentSource, which hands them to UiBundle's thumbnail check. Matched on the stored name rather than on a column of its own: it is the very path the file is served under, and the only thing saying what the file is
    /** @return Media[] */
    public function findPdfs(): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.name LIKE :pdf')
            ->setParameter('pdf', '%.pdf')
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
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
