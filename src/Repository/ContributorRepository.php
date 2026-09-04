<?php

namespace c975L\BookBundle\Repository;

use c975L\BookBundle\Entity\Contributor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
        return $this->createQueryBuilder('c')
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
