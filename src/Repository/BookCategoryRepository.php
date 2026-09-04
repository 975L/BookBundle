<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Repository;

use c975L\BookBundle\Entity\BookCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BookCategory>
 */
class BookCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookCategory::class);
    }

    // The owners of the given Block rows, their blocks joined: what the front-end "Edit this block" hover button needs to reach the screen a block is composed on (see BookBlockEditUrlProvider)
    /**
     * @param int[] $blockIds
     *
     * @return BookCategory[]
     */
    public function findByBlockIds(array $blockIds): array
    {
        if ([] === $blockIds) {
            return [];
        }

        return $this->createQueryBuilder('e')
            ->select('e, b')
            ->innerJoin('e.blocks', 'b')
            ->andWhere('b.id IN (:blockIds)')
            ->setParameter('blockIds', $blockIds)
            ->getQuery()
            ->getResult()
        ;
    }

    // The public listing, read by the front, by the sitemap and by the link picker alike - a category trashed or set aside belongs to none of the three (see Entity\Trait\HideableTrait). The back office lists them all, EasyAdmin building its own query
    /**
     * @return BookCategory[]
     */
    public function findAll(?int $number = null): array
    {
        $query = $this->createQueryBuilder('c')
            ->andWhere('c.isDeleted = false')
            ->andWhere('c.hidden = false')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.title', 'ASC')
        ;

        if (null !== $number) {
            $query->setMaxResults($number);
        }

        return $query->getQuery()->getResult();
    }

    // The categories a visitor can actually open: one holding nothing the site shows heads a page with an empty list under it, the same rule SerieRepository::findWithBooks() applies to the series. The conditions on the book are the catalog's own, the serie and the publication date included - a book of a serie set aside, or one still to come out, is no more shown here than it is in the catalog (see BookRepository::publishedQueryBuilder()), and a category holding only those has nothing to list
    /**
     * @return BookCategory[]
     */
    public function findWithBooks(): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.books', 'b', 'WITH', 'b.isDeleted = false AND b.hidden = false AND b.newerVersion IS NULL AND b.published IS NOT NULL AND b.published <= :now')
            ->leftJoin('b.serie', 'serie')
            ->andWhere('serie IS NULL OR serie.hidden = false')
            ->andWhere('c.isDeleted = false')
            ->andWhere('c.hidden = false')
            ->setParameter('now', new \DateTime())
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.title', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
