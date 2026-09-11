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
use c975L\BookBundle\Entity\BookContributor;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Serie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contributor>
 */
class ContributorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contributor::class);
    }

    // The owners of the given Block rows, their blocks joined: what the front-end "Edit this block" hover button needs to reach the screen a block is composed on (see BookBlockEditUrlProvider)
    /**
     * @param int[] $blockIds
     *
     * @return Contributor[]
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

    /**
     * @return Contributor[] Returns an array of Contributor objects
     */
    public function findAll(?int $number = null): array
    {
        $query = $this->createQueryBuilder('c')
            ->andWhere('c.isDeleted = false')
            // The public listing, read by the front, by the sitemap and by the link picker alike - a person set aside belongs to none of the three (see Entity\Trait\HideableTrait). The back-office lists them all, EasyAdmin building its own query
            ->andWhere('c.hidden = false')
            ->orderBy('c.position', \SortDirection::Ascending)
            ->addOrderBy('c.name', \SortDirection::Ascending)
        ;

        if (null !== $number) {
            $query->setMaxResults($number);
        }

        return $query->getQuery()->getResult();
    }

    // The people the index lists: the ones a shown book or a shown serie still credits. Someone recorded but credited nowhere yet heads a page with nothing under it, so the index leaves them out - their screen still holds them, and they appear the day a book names them
    /**
     * @return Contributor[]
     */
    public function findCredited(): array
    {
        $contributors = $this->createQueryBuilder('c')
            ->leftJoin('c.medias', 'm')
            ->addSelect('m')
            ->andWhere('c.isDeleted = false')
            ->andWhere('c.hidden = false')
            // What credits them, asked as five existence tests rather than five joins: joined, the five collections of one person crossed one another row by row before a GROUP BY threw the crossing away, thirteen people costing five million rows and forty-five seconds. Nothing here is read, only answered yes or no, so a test says it at the price of a lookup. The parts held by a row count as much as the two columns: a narrator or a translator is credited nowhere else, and the index would list nobody it did not also record as an author
            ->andWhere(
                'EXISTS (SELECT ab.id FROM ' . Book::class . ' ab WHERE ab.author = c AND ab.isDeleted = false AND ab.hidden = false AND ab.newerVersion IS NULL)'
                . ' OR EXISTS (SELECT ib.id FROM ' . Book::class . ' ib WHERE ib.illustrator = c AND ib.isDeleted = false AND ib.hidden = false AND ib.newerVersion IS NULL)'
                . ' OR EXISTS (SELECT asr.id FROM ' . Serie::class . ' asr WHERE asr.author = c AND asr.isDeleted = false AND asr.hidden = false)'
                . ' OR EXISTS (SELECT isr.id FROM ' . Serie::class . ' isr WHERE isr.illustrator = c AND isr.isDeleted = false AND isr.hidden = false)'
                . ' OR EXISTS (SELECT cr.id FROM ' . BookContributor::class . ' cr JOIN cr.book cb WHERE cr.contributor = c AND cb.isDeleted = false AND cb.hidden = false AND cb.newerVersion IS NULL)'
            )
            ->orderBy('c.position', \SortDirection::Ascending)
            ->addOrderBy('c.name', \SortDirection::Ascending)
            ->getQuery()
            ->getResult()
        ;

        $this->preloadRoles($contributors);

        return $contributors;
    }

    // The collections Contributor::getRoles() reads, filled one query each instead of one query per person: the existence tests above read nothing, so a listing printing the parts under every name asked Doctrine for the same four collections again, person by person, and one more query for each credited book. Read whole and unfiltered, unlike what those tests match on: a collection filled here has to say exactly what it says when it is read on its own, holdsContent() counting the very rows getRoles() sets aside - sorting out what the catalog shows stays where it was, in getRoles()
    /**
     * @param Contributor[] $contributors
     */
    private function preloadRoles(array $contributors): void
    {
        if ([] === $contributors) {
            return;
        }

        // The books they signed or illustrated, each one's replaced version joined: an inverse one-to-one cannot be a lazy proxy - it has to be read to be known absent - so a book filled without it costs one more query, the very reason BookRepository::publishedQueryBuilder() joins it too (see Book::$previousVersion)
        foreach (['authoredBooks', 'illustratedBooks'] as $association) {
            $this->preloadQueryBuilder($contributors)
                ->leftJoin('c.' . $association, 'books')
                ->addSelect('books')
                ->leftJoin('books.previousVersion', 'booksPreviousVersion')
                ->addSelect('booksPreviousVersion')
                ->getQuery()
                ->getResult()
            ;
        }

        // The series, which carry no such link and are read as they are
        foreach (['authoredSeries', 'illustratedSeries'] as $association) {
            $this->preloadQueryBuilder($contributors)
                ->leftJoin('c.' . $association, 'series')
                ->addSelect('series')
                ->getQuery()
                ->getResult()
            ;
        }

        // The credited parts and the book each of them names, in the same query: getRoles() reads the book of every credit, and a book left as a proxy costs one query per row
        $this->preloadQueryBuilder($contributors)
            ->leftJoin('c.credits', 'credit')
            ->addSelect('credit')
            ->leftJoin('credit.book', 'creditedBook')
            ->addSelect('creditedBook')
            ->leftJoin('creditedBook.previousVersion', 'creditedBookPreviousVersion')
            ->addSelect('creditedBookPreviousVersion')
            ->getQuery()
            ->getResult()
        ;
    }

    // The people every preload query reads on, the collection it fills being the only thing that changes from one to the next
    /**
     * @param Contributor[] $contributors
     */
    private function preloadQueryBuilder(array $contributors): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c IN (:contributors)')
            ->setParameter('contributors', $contributors)
        ;
    }

    // A person with everything they are credited on. Looked up whatever their state - someone in the trash has to be found for their page to answer 410 rather than a plain 404 (see ContributorController::display()) - where what they are credited on leaves them as soon as it is trashed or set aside. A book replaced by a newer version leaves their page as it left the catalog: it keeps its own page, reached from the search or from the version replacing it (see Book::$newerVersion)
    public function findOneBySlugWithWorks(string $slug): ?Contributor
    {
        // The person and their portrait, the one collection this query carries: what the page prints around them - blocks, books, series, credits - is filled below, one query each
        $contributor = $this->createQueryBuilder('c')
            ->leftJoin('c.medias', 'm')
            ->addSelect('m')
            ->andWhere('c.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (null === $contributor) {
            return null;
        }

        $this->preloadWorks($contributor);

        return $contributor;
    }

    // What a person's page is made of, filled one collection at a time rather than in the single query this used to be: the five collections crossed one another row by row, and someone signing sixty books turned six hundred covers into billions of rows - a temporary table MariaDB gave up writing after a hundred seconds. The same lesson findCredited() drew from its own five joins, and the same remedy; unlike preloadRoles() though, each collection is filtered here, a page showing only what the catalog still shows
    private function preloadWorks(Contributor $contributor): void
    {
        // Where the site says the rest in its own words, read by the template as contributor.blocks
        $this->preloadQueryBuilder([$contributor])
            ->leftJoin('c.blocks', 'blocks')
            ->addSelect('blocks')
            ->getQuery()
            ->getResult()
        ;

        // The books they signed or illustrated and the covers their cards print, each one's replaced version joined for the reason preloadRoles() joins it: an inverse one-to-one cannot be a lazy proxy, so a book filled without it costs one more query (see Book::$previousVersion)
        foreach (['authoredBooks', 'illustratedBooks'] as $association) {
            $this->preloadQueryBuilder([$contributor])
                ->leftJoin('c.' . $association, 'books', 'WITH', 'books.isDeleted = false AND books.hidden = false AND books.newerVersion IS NULL')
                ->addSelect('books')
                ->leftJoin('books.medias', 'booksMedias')
                ->addSelect('booksMedias')
                ->leftJoin('books.previousVersion', 'booksPreviousVersion')
                ->addSelect('booksPreviousVersion')
                ->getQuery()
                ->getResult()
            ;
        }

        // The series, which carry no such link and are read as they are
        foreach (['authoredSeries', 'illustratedSeries'] as $association) {
            $this->preloadQueryBuilder([$contributor])
                ->leftJoin('c.' . $association, 'series', 'WITH', 'series.isDeleted = false AND series.hidden = false')
                ->addSelect('series')
                ->leftJoin('series.medias', 'seriesMedias')
                ->addSelect('seriesMedias')
                ->getQuery()
                ->getResult()
            ;
        }

        // What they narrated or translated, read under the same conditions as what they signed - Contributor::getBooks() merges the three
        $this->preloadQueryBuilder([$contributor])
            ->leftJoin('c.credits', 'credit')
            ->addSelect('credit')
            ->leftJoin('credit.book', 'creditedBook', 'WITH', 'creditedBook.isDeleted = false AND creditedBook.hidden = false AND creditedBook.newerVersion IS NULL')
            ->addSelect('creditedBook')
            ->leftJoin('creditedBook.medias', 'creditedBookMedias')
            ->addSelect('creditedBookMedias')
            ->leftJoin('creditedBook.previousVersion', 'creditedBookPreviousVersion')
            ->addSelect('creditedBookPreviousVersion')
            ->getQuery()
            ->getResult()
        ;
    }

    // The person a name stands for, which is what the migration off the four string columns matches on, and what an import falls back to when the archive predates this entity (see ContributorImportProvider). Never someone in the trash: an import would credit them on a book, their page would answer 410, and the foreign key would then refuse to delete them for good
    public function findOneByName(string $name): ?Contributor
    {
        return $this->findOneBy(['name' => $name, 'isDeleted' => false]);
    }
}
