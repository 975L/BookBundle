<?php

namespace c975L\BookBundle\Repository;

use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Enum\SerieKind;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Serie>
 */
class SerieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Serie::class);
    }

    // The owners of the given Block rows, their blocks joined: what the front-end "Edit this block" hover button needs to reach the screen a block is composed on (see BookBlockEditUrlProvider)
    /**
     * @param int[] $blockIds
     *
     * @return Serie[]
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
     * @return Serie[] Returns an array of Serie objects
     */
    public function findAll(?int $number = null): array
    {
        $query = $this->createQueryBuilder('s')
            ->andWhere('s.isDeleted = false')
            // The public listing, read by the front, by the sitemap and by the link picker alike - a serie set aside belongs to none of the three (see Entity\Trait\HideableTrait). The back-office lists them all, EasyAdmin building its own query
            ->andWhere('s.hidden = false')
            ->orderBy('s.position', 'ASC')
            ->addOrderBy('s.title', 'ASC')
        ;

        if (null !== $number) {
            $query->setMaxResults($number);
        }

        return $query->getQuery()->getResult();
    }

    // The two lists below share no serie: a serie is filed under the kind it declares, and under what it holds when it declares none (see SerieKind). That is what keeps the books' index and the planches' one from listing the same serie twice
    /**
     * @return Serie[] Series of books holding at least one book
     */
    public function findWithBooks(): array
    {
        return $this->createQueryBuilder('s')
            // On the join too: a serie whose every book is set aside holds none the index could list, and would head a section with nothing under it
            ->innerJoin('s.books', 'b', 'WITH', 'b.isDeleted = false AND b.hidden = false AND b.newerVersion IS NULL')
            ->leftJoin('s.medias', 'm')
            ->addSelect('m')
            ->andWhere('s.isDeleted = false')
            ->andWhere('s.hidden = false')
            ->andWhere('s.kind IS NULL OR s.kind = :kind')
            ->setParameter('kind', SerieKind::Book->value)
            ->orderBy('s.position', 'ASC')
            ->addOrderBy('s.title', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Serie[] Series of planches holding at least one planche
     */
    public function findWithStrips(): array
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.strips', 'st', 'WITH', 'st.isDeleted = false AND st.hidden = false')
            ->leftJoin('s.medias', 'm')
            ->addSelect('m')
            ->andWhere('s.isDeleted = false')
            ->andWhere('s.hidden = false')
            ->andWhere('s.kind IS NULL OR s.kind = :kind')
            ->setParameter('kind', SerieKind::Strip->value)
            ->orderBy('s.position', 'ASC')
            ->addOrderBy('s.title', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    // A serie with its books sorted, the unpublished ones first. The serie itself is looked up whatever its state - a serie in the trash has to be found for its page to answer 410 rather than a plain 404 (see SerieController::display()) - where the books it lists leave it as soon as they are trashed
    // A book replaced by a newer version leaves the list of its serie, as it left the catalog: it keeps its page, reached from the search or from the version replacing it (see Book::$newerVersion)
    public function findOneBySlugWithSortedBooks(string $slug): ?Serie
    {
        return $this->createQueryBuilder('s')
            ->select('s', 'b', 'bm')
            ->leftJoin('s.books', 'b', 'WITH', 'b.isDeleted = false AND b.hidden = false AND b.newerVersion IS NULL')
            ->leftJoin('b.medias', 'bm')
            ->where('s.slug = :slug')
            ->setParameter('slug', $slug)
            ->addSelect('CASE WHEN b.published IS NULL THEN 1 ELSE 0 END AS HIDDEN sortNull')
            ->orderBy('sortNull', 'DESC') // NULLs en premier
            ->addOrderBy('b.published', 'DESC') // Les plus récents ensuite
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
