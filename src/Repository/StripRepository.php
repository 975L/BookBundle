<?php

namespace c975L\BookBundle\Repository;

use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Strip>
 *
 * @method Strip|null find($id, $lockMode = null, $lockVersion = null)
 * @method Strip|null findOneBy(array $criteria, array $orderBy = null)
 * @method Strip[]    findAll()
 * @method Strip[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StripRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Strip::class);
    }

    public function findAllPublished(?int $number = null): array
    {
        $query = $this->createQueryBuilder('s')
            ->andWhere('s.published IS NOT NULL')
            ->andWhere('s.published <= :now')
            ->orderBy('s.published', 'DESC')
            ->setParameter('now', new \DateTime())
        ;

        if (null !== $number) {
            $query->setMaxResults($number);
        }

        return $query->getQuery()->getResult();
    }

    public function findAllPublishedBySerie(Serie $serie, ?int $number = null): array
    {
        $query = $this->createQueryBuilder('s')
            ->andWhere('s.serie = :serie')
            ->andWhere('s.published IS NOT NULL')
            ->andWhere('s.published <= :now')
            ->orderBy('s.number', 'ASC')
            ->addOrderBy('s.published', 'ASC')
            ->setParameter('serie', $serie)
            ->setParameter('now', new \DateTime())
        ;

        if (null !== $number) {
            $query->setMaxResults($number);
        }

        return $query->getQuery()->getResult();
    }

    public function findAllByCharacter(string $character): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere("CONCAT(',', s.charactersSlug, ',') LIKE :character")
            ->andWhere('s.published IS NOT NULL')
            ->andWhere('s.published <= :now')
            ->orderBy('s.published', 'DESC')
            ->setParameter('character', '%,' . $character . ',%')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult()
        ;
    }

    public function findPreviousNext(Strip $strip): array
    {
        $previous = $this->createQueryBuilder('s')
            ->andWhere('s.serie = :serie')
            ->andWhere('s.published IS NOT NULL')
            ->andWhere('s.published <= :now')
            ->andWhere('s.published < :current OR (s.published = :current AND s.id < :id)')
            ->orderBy('s.published', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults(1)
            ->setParameter('serie', $strip->getSerie())
            ->setParameter('now', new \DateTime())
            ->setParameter('current', $strip->getPublished())
            ->setParameter('id', $strip->getId())
            ->getQuery()
            ->getOneOrNullResult()
        ;

        $next = $this->createQueryBuilder('s')
            ->andWhere('s.serie = :serie')
            ->andWhere('s.published IS NOT NULL')
            ->andWhere('s.published <= :now')
            ->andWhere('s.published > :current OR (s.published = :current AND s.id > :id)')
            ->orderBy('s.published', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->setMaxResults(1)
            ->setParameter('serie', $strip->getSerie())
            ->setParameter('now', new \DateTime())
            ->setParameter('current', $strip->getPublished())
            ->setParameter('id', $strip->getId())
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return ['previous' => $previous, 'next' => $next];
    }

    public function search(string $query): array
    {
        if (empty($query)) {
            return [];
        }

        return $this->createQueryBuilder('s')
            ->andWhere('s.title LIKE :query OR s.characters LIKE :query')
            ->andWhere('s.published IS NOT NULL')
            ->andWhere('s.published <= :now')
            ->setParameter('now', new \DateTime())
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('s.published', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
}
