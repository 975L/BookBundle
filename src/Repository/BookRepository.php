<?php

namespace c975L\BookBundle\Repository;

use c975L\BookBundle\Entity\Book;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Book>
 *
 * @method Book|null find($id, $lockMode = null, $lockVersion = null)
 * @method Book|null findOneBy(array $criteria, array $orderBy = null)
 * @method Book[]    findAll()
 * @method Book[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Book::class);
    }

    /**
    * @return Book[] Returns an array of Book objects
    */
    public function findAllPublished(?int $number = null): array
    {
        $query = $this->createQueryBuilder('b')
            ->andWhere('b.published IS NOT NULL')
            ->andWhere('b.published <= :now')
            ->orderBy('b.published', 'DESC')
            ->setParameter('now', new \DateTime())
        ;

        if (null !== $number) {
            $query->setMaxResults($number);
        }

        return $query->getQuery()->getResult();
    }

    /**
    * @return Book[] Returns an array of Book objects
    */
    public function findAllToBePublished(): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.published > :now OR b.published IS NULL')
            ->orderBy('b.published', 'DESC')
            ->orderBy('b.id', 'DESC')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult()
        ;
    }

    // Finds books based on search
    public function search(string $query): array
    {
        if (empty($query)) {
            return [];
        }

        return $this->createQueryBuilder('b')
            ->andWhere('b.title LIKE :query')
            ->andWhere('b.published IS NOT NULL')
            ->andWhere('b.published <= :now')
            ->setParameter('now', new \DateTime())
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('b.published', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    //    /**
    //     * @return Book[] Returns an array of Book objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('b.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Book
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
