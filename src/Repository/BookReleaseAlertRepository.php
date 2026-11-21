<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Repository;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookReleaseAlert;
use c975L\BookBundle\Entity\Strip;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BookReleaseAlert>
 */
class BookReleaseAlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookReleaseAlert::class);
    }

    // The row a visitor subscribing twice already has, the unique constraint on (book, email) leaving no second one to create
    public function findOneByBookAndEmail(Book $book, string $email): ?BookReleaseAlert
    {
        return $this->findOneBy(['book' => $book, 'email' => $email]);
    }

    // Same row for a planche, the unique constraint on (strip, email) leaving no second one either
    public function findOneByStripAndEmail(Strip $strip, string $email): ?BookReleaseAlert
    {
        return $this->findOneBy(['strip' => $strip, 'email' => $email]);
    }

    // The subscriptions whose book or planche has come out, read in the database rather than filtered in PHP: the conditions are BookRepository's and StripRepository's publishedQueryBuilder() own, so what is set aside, of a serie set aside, in the trash or replaced is not published and nobody is written to. Ordered by the refusals counted on each address first, a row whose send keeps failing going to the back rather than filling every batch (see BookReleaseAlert::$attempts)
    /** @return BookReleaseAlert[] */
    public function findReleased(int $limit): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.book', 'b')
            ->leftJoin('b.serie', 'bookSerie')
            ->leftJoin('a.strip', 's')
            ->leftJoin('s.serie', 'stripSerie')
            ->andWhere(
                '(b.id IS NOT NULL AND b.isDeleted = false AND b.hidden = false AND b.newerVersion IS NULL'
                . ' AND (bookSerie IS NULL OR bookSerie.hidden = false) AND b.published IS NOT NULL AND b.published <= :now)'
                . ' OR (s.id IS NOT NULL AND s.isDeleted = false AND s.hidden = false'
                . ' AND (stripSerie IS NULL OR stripSerie.hidden = false) AND s.published IS NOT NULL AND s.published <= :now)'
            )
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('a.attempts', \SortDirection::Ascending)
            ->addOrderBy('a.id', \SortDirection::Ascending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    // Everything still waiting, the rows being deleted as they are sent: what the command prints so a queue that stops going down is seen
    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    // The waiting lists of books or planches that never came out, dropped wholesale: an address kept for a parution that never happens is an address kept for nothing
    public function deleteCreatedBefore(\DateTimeImmutable $retentionDate): int
    {
        return (int) $this->createQueryBuilder('a')
            ->delete()
            ->andWhere('a.createdAt < :retentionDate')
            ->setParameter('retentionDate', $retentionDate)
            ->getQuery()
            ->execute()
        ;
    }
}
