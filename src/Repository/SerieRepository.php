<?php

namespace c975L\BookBundle\Repository;

use c975L\BookBundle\Entity\Serie;
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

    /**
    * @return Serie[] Returns an array of Serie objects
    */
    public function findAll(?int $number = null): array
    {
        $query = $this->createQueryBuilder('s')
            ->orderBy('s.title', 'ASC')
        ;

        if (null !== $number) {
            $query->setMaxResults($number);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Récupère une série avec ses livres triés (non publiés en premier)
     */
    public function findOneBySlugWithSortedBooks(string $slug): ?Serie
    {
        return $this->createQueryBuilder('s')
            ->select('s', 'b', 'bm')
            ->leftJoin('s.books', 'b')
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

    //    /**
    //     * @return Serie[] Returns an array of Serie objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Serie
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
