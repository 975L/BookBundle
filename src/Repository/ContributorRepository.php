<?php

namespace c975L\BookBundle\Repository;

use c975L\BookBundle\Entity\Contributor;
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
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.name', 'ASC')
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
            ->leftJoin('c.authoredBooks', 'ab', 'WITH', 'ab.isDeleted = false AND ab.hidden = false AND ab.newerVersion IS NULL')
            ->leftJoin('c.illustratedBooks', 'ib', 'WITH', 'ib.isDeleted = false AND ib.hidden = false AND ib.newerVersion IS NULL')
            ->leftJoin('c.authoredSeries', 'asr', 'WITH', 'asr.isDeleted = false AND asr.hidden = false')
            ->leftJoin('c.illustratedSeries', 'isr', 'WITH', 'isr.isDeleted = false AND isr.hidden = false')
            // The parts held by a row rather than by a column count as much: a narrator or a translator is credited nowhere else, and the index would list nobody it did not also record as an author
            ->leftJoin('c.credits', 'cr')
            ->leftJoin('cr.book', 'cb', 'WITH', 'cb.isDeleted = false AND cb.hidden = false AND cb.newerVersion IS NULL')
            ->leftJoin('c.medias', 'm')
            ->addSelect('m')
            ->andWhere('c.isDeleted = false')
            ->andWhere('c.hidden = false')
            ->andWhere('ab.id IS NOT NULL OR ib.id IS NOT NULL OR asr.id IS NOT NULL OR isr.id IS NOT NULL OR cb.id IS NOT NULL')
            ->groupBy('c.id')
            ->addGroupBy('m.id')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        $this->preloadRoles($contributors);

        return $contributors;
    }

    // The collections Contributor::getRoles() reads, filled one query each instead of one query per person: the joins above only serve the filter, so a listing printing the parts under every name asked Doctrine for the same four collections again, person by person, and one more query for each credited book. Read whole and unfiltered, unlike those joins: a collection filled here has to say exactly what it says when it is read on its own, holdsContent() counting the very rows getRoles() sets aside - sorting out what the catalog shows stays where it was, in getRoles()
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

    // A person with everything they are credited on, their books' own covers joined. Looked up whatever their state - someone in the trash has to be found for their page to answer 410 rather than a plain 404 (see ContributorController::display()) - where what they are credited on leaves them as soon as it is trashed or set aside. A book replaced by a newer version leaves their page as it left the catalog: it keeps its own page, reached from the search or from the version replacing it (see Book::$newerVersion)
    public function findOneBySlugWithWorks(string $slug): ?Contributor
    {
        return $this->createQueryBuilder('c')
            ->select('c', 'ab', 'ib', 'abm', 'ibm', 'asr', 'isr', 'asrm', 'isrm', 'cr', 'cb', 'cbm')
            ->leftJoin('c.authoredBooks', 'ab', 'WITH', 'ab.isDeleted = false AND ab.hidden = false AND ab.newerVersion IS NULL')
            ->leftJoin('ab.medias', 'abm')
            ->leftJoin('c.illustratedBooks', 'ib', 'WITH', 'ib.isDeleted = false AND ib.hidden = false AND ib.newerVersion IS NULL')
            ->leftJoin('ib.medias', 'ibm')
            ->leftJoin('c.authoredSeries', 'asr', 'WITH', 'asr.isDeleted = false AND asr.hidden = false')
            ->leftJoin('asr.medias', 'asrm')
            ->leftJoin('c.illustratedSeries', 'isr', 'WITH', 'isr.isDeleted = false AND isr.hidden = false')
            ->leftJoin('isr.medias', 'isrm')
            // What they narrated or translated, read under the same conditions as what they signed - Contributor::getBooks() merges the three
            ->leftJoin('c.credits', 'cr')
            ->leftJoin('cr.book', 'cb', 'WITH', 'cb.isDeleted = false AND cb.hidden = false AND cb.newerVersion IS NULL')
            ->leftJoin('cb.medias', 'cbm')
            ->andWhere('c.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    // The person a name stands for, which is what the migration off the four string columns matches on, and what an import falls back to when the archive predates this entity (see ContributorImportProvider). Never someone in the trash: an import would credit them on a book, their page would answer 410, and the foreign key would then refuse to delete them for good
    public function findOneByName(string $name): ?Contributor
    {
        return $this->findOneBy(['name' => $name, 'isDeleted' => false]);
    }
}
