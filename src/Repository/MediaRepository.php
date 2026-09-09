<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

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

    // The rows naming a stored file, whatever the owner they hang off - what the declared-files health check walks (see UiBundle's AbstractDeclaredFilesHealthCheckProvider)
    /** @return Media[] */
    public function findWithFilename(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.name IS NOT NULL AND m.name != :empty')
            ->setParameter('empty', '')
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
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
