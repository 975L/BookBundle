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

    // The owners of the given Block rows, their blocks joined: what the front-end "Edit this block" hover button needs to reach the screen a block is composed on (see BookBlockEditUrlProvider)
    /**
     * @param int[] $blockIds
     *
     * @return Strip[]
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

    public function findAllPublished(?int $number = null): array
    {
        $query = $this->createQueryBuilder('s')
            ->andWhere('s.isDeleted = false')
            ->andWhere('s.published IS NOT NULL')
            ->andWhere('s.published <= :now')
            ->orderBy('s.published', 'DESC')
            // Two planches published the same day are read newest first all the same, on the id the navigation from one to the next ties on too (see findPreviousNext) - a number is nullable where an id never is
            ->addOrderBy('s.id', 'DESC')
            ->setParameter('now', new \DateTime())
        ;

        if (null !== $number) {
            $query->setMaxResults($number);
        }

        return $query->getQuery()->getResult();
    }

    // "$character": the same listing, narrowed down to who speaks - the filter the chips of a serie's page lay (see Strip:Characters)
    public function findAllPublishedBySerie(Serie $serie, ?int $number = null, ?string $character = null): array
    {
        $query = $this->createQueryBuilder('s')
            ->andWhere('s.serie = :serie')
            ->andWhere('s.isDeleted = false')
            ->andWhere('s.published IS NOT NULL')
            ->andWhere('s.published <= :now')
            // The latest published first, as in the listing of all planches: a serie holding hundreds of them opens on what has just come out
            ->orderBy('s.number', 'DESC')
            ->addOrderBy('s.published', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setParameter('serie', $serie)
            ->setParameter('now', new \DateTime())
        ;

        if (null !== $character && '' !== $character) {
            $query
                ->andWhere("CONCAT(',', s.charactersSlug, ',') LIKE :character")
                ->setParameter('character', '%,' . $character . ',%')
            ;
        }

        if (null !== $number) {
            $query->setMaxResults($number);
        }

        return $query->getQuery()->getResult();
    }

    public function findAllByCharacter(string $character): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere("CONCAT(',', s.charactersSlug, ',') LIKE :character")
            ->andWhere('s.isDeleted = false')
            ->andWhere('s.published IS NOT NULL')
            ->andWhere('s.published <= :now')
            ->orderBy('s.published', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setParameter('character', '%,' . $character . ',%')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult()
        ;
    }

    // The planche a number leads to, which a numbered url and a short link both ask for (see StripController::shortcut()) - a planche has no version replacing it, so its number names it alone
    // The same filters as the rest of the catalog: a number leads to what reads today, never to a draft or to a planche in the trash - which would answer 404 while the published planche bearing that number would never be reached
    public function findOneByNumber(int $number): ?Strip
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.number = :number')
            ->andWhere('s.isDeleted = false')
            ->andWhere('s.published IS NOT NULL')
            ->andWhere('s.published <= :now')
            ->setParameter('number', $number)
            ->setParameter('now', new \DateTime())
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findPreviousNext(Strip $strip): array
    {
        $previous = $this->createQueryBuilder('s')
            ->andWhere('s.serie = :serie')
            ->andWhere('s.isDeleted = false')
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
            ->andWhere('s.isDeleted = false')
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

        // At either end the serie loops rather than stopping: from the last planche the reader goes back to the first, and the bar keeps its three places filled instead of showing a hole. A serie of one loops on itself, which is no navigation at all, so it keeps its nulls
        $previous ??= $this->findEnd($strip, 'DESC');
        $next ??= $this->findEnd($strip, 'ASC');

        return ['previous' => $previous, 'next' => $next];
    }

    // The first or the last planche of the serie the given one belongs to, whichever end the loop asks for
    private function findEnd(Strip $strip, string $direction): ?Strip
    {
        $end = $this->createQueryBuilder('s')
            ->andWhere('s.serie = :serie')
            ->andWhere('s.isDeleted = false')
            ->andWhere('s.published IS NOT NULL')
            ->andWhere('s.published <= :now')
            ->orderBy('s.published', $direction)
            ->addOrderBy('s.id', $direction)
            ->setMaxResults(1)
            ->setParameter('serie', $strip->getSerie())
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $end === $strip ? null : $end;
    }

    /**
     * The characters speaking in one serie, each named once - what its own listing offers to filter on, read off the field rather than by loading every planche.
     *
     * @return array<int, array{name: string, slug: string}>
     */
    public function findCharactersBySerie(Serie $serie): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('DISTINCT s.characters')
            ->andWhere('s.serie = :serie')
            ->andWhere('s.isDeleted = false')
            ->andWhere('s.published IS NOT NULL')
            ->andWhere('s.published <= :now')
            ->setParameter('serie', $serie)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getScalarResult()
        ;

        $characters = [];

        foreach ($rows as $row) {
            foreach (Strip::splitCharacters($row['characters'] ?? null) as $character) {
                // Keyed by slug: the same name spelled with another case or another accent leads to the same page, and two chips for it would read as two characters
                $characters[$character['slug']] = $character;
            }
        }

        uasort($characters, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);

        return array_values($characters);
    }

    // "$serieId": the search a serie's own page carries, which looks inside that serie alone - the same field asks the whole site elsewhere (see strip/index.html.twig)
    public function search(string $query, ?int $serieId = null): array
    {
        if (empty($query)) {
            return [];
        }

        $builder = $this->createQueryBuilder('s');

        if (null !== $serieId) {
            $builder
                ->andWhere('s.serie = :serie')
                ->setParameter('serie', $serieId)
            ;
        }

        return $builder
            // The summary too: a planche is looked for by what is said in it as much as by its title or by who says it - and a title numbering the plate ("Réplique 042") answers nothing a visitor would type
            ->andWhere('s.title LIKE :query OR s.characters LIKE :query OR s.summary LIKE :query')
            ->andWhere('s.isDeleted = false')
            ->andWhere('s.published IS NOT NULL')
            ->andWhere('s.published <= :now')
            ->setParameter('now', new \DateTime())
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('s.published', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
}
