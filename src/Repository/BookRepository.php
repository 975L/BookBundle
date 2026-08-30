<?php

namespace c975L\BookBundle\Repository;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookEdition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Book>
 *
 * @method Book|null find($id, $lockMode = null, $lockVersion = null)
 * @method Book|null findOneBy(array $criteria, array $orderBy = null)
 * @method Book[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Book::class);
    }

    // The owners of the given Block rows, their blocks joined: what the front-end "Edit this block" hover button needs to reach the screen a block is composed on (see BookBlockEditUrlProvider)
    /**
     * @param int[] $blockIds
     *
     * @return Book[]
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

    // Overrides the Doctrine inheritance so that no caller ever gets the trashed books back
    /**
     * @return Book[] Returns an array of Book objects
     */
    public function findAll(): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.isDeleted = false')
            ->orderBy('b.title', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Book[] Returns an array of Book objects
     */
    public function findAllPublished(?int $number = null, ?string $language = null): array
    {
        $query = $this->publishedQueryBuilder($language);

        if (null !== $number) {
            $query->setMaxResults($number);
        }

        return $query->getQuery()->getResult();
    }

    // Every book whose page answers, the ones a newer version replaces included: what a sitemap lists and what a link checker walks, where the catalog above lists only what a reader browses (see Book::$newerVersion)
    /**
     * @return Book[] Returns an array of Book objects
     */
    public function findAllOnline(): array
    {
        return $this->publishedQueryBuilder(null, true)
            ->getQuery()
            ->getResult()
        ;
    }

    // The books behind a set of ids, whatever the order asked for, and only the ones a visitor may reach: what a wishlist and a review page both need before naming one (see BookFavoriteItemProvider). Replaced versions included, their page still answering
    /**
     * @param int[] $ids
     *
     * @return Book[]
     */
    public function findPublishedByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->publishedQueryBuilder(null, true)
            ->andWhere('b.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult()
        ;
    }

    // How many books are out, without building a single one of them - what a block asking for "the catalog" needs to know before it decides to print anything
    public function countPublished(?string $language = null): int
    {
        return (int) $this->publishedQueryBuilder($language)
            ->select('COUNT(b.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    // The book a number leads to, which a numbered url and a short link both ask for (see BookController::shortcut()). Two rows can carry the same number - a version and the one replacing it - so the one still listed answers, a reader following a number being meant to land on what the catalog reads today (see Book::$newerVersion)
    public function findOneByNumber(int $number): ?Book
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.serie', 'serie')
            ->andWhere('b.number = :number')
            ->andWhere('b.newerVersion IS NULL')
            ->andWhere('b.isDeleted = false')
            ->andWhere('b.hidden = false')
            ->andWhere('serie IS NULL OR serie.hidden = false')
            ->setParameter('number', $number)
            // Two books left sharing a number - a site numbering each language apart - answer in a fixed order rather than in the one the database happens to return
            ->orderBy('b.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    // The catalog: every book a reader can open, the most recently published first. A book is out when its date has come - the only one it carries, its editions having none (see BookEdition)
    // A book replaced by a newer version is not listed: it keeps its page, its ISBN and its place in the sitemap, and is reached from the version that replaces it (see Book::$newerVersion)
    private function publishedQueryBuilder(?string $language = null, bool $includeReplaced = false): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('b')
            ->leftJoin('b.serie', 'serie')
            ->andWhere('b.isDeleted = false')
            // A book set aside by its editor leaves every one of these lists, the sitemap included, until the box is unticked (see Entity\Trait\HideableTrait)
            ->andWhere('b.hidden = false')
            // A book of a serie set aside is no more shown than the serie telling it: reading it here covers every way a row is written - the form, the index switch, the import and the fixtures - where a guard on each of them would not
            ->andWhere('serie IS NULL OR serie.hidden = false')
            ->andWhere('b.published IS NOT NULL AND b.published <= :now')
            ->orderBy('b.published', 'DESC')
            ->setParameter('now', new \DateTime())
        ;

        // A book replaced by a newer version keeps its page and its place in the sitemap, it only stops being browsed
        if (!$includeReplaced) {
            $queryBuilder->andWhere('b.newerVersion IS NULL');
        }

        // A book and its translation are two rows: a list mixing them reads as a bug to a visitor looking for their own language
        if (null !== $language) {
            $queryBuilder
                ->andWhere('b.language = :language')
                ->setParameter('language', $language)
            ;
        }

        return $queryBuilder;
    }

    /**
     * @return Book[] Returns an array of Book objects
     */
    public function findAllToBePublished(): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.serie', 'serie')
            ->andWhere('b.isDeleted = false')
            ->andWhere('b.newerVersion IS NULL')
            // The block announcing what is coming is a public one: a book set aside, or one of a serie set aside, is no more announced than it is listed
            ->andWhere('b.hidden = false')
            ->andWhere('serie IS NULL OR serie.hidden = false')
            ->andWhere('b.published > :now OR b.published IS NULL')
            ->orderBy('b.published', 'DESC')
            ->addOrderBy('b.id', 'DESC')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult()
        ;
    }

    // The languages the catalog is actually written in, so a list can be offered per language without any site having to declare which ones it publishes
    /** @return list<string> */
    public function findLanguages(): array
    {
        $rows = $this->createQueryBuilder('b')
            ->select('DISTINCT b.language')
            ->leftJoin('b.serie', 'serie')
            ->andWhere('b.isDeleted = false')
            // A language whose every book is set aside is no language the catalog is written in any more, and would offer a list with nothing in it
            ->andWhere('b.hidden = false')
            ->andWhere('serie IS NULL OR serie.hidden = false')
            ->andWhere('b.language IS NOT NULL')
            ->orderBy('b.language', 'ASC')
            ->getQuery()
            ->getSingleColumnResult()
        ;

        return array_values(array_filter($rows, static fn (?string $language): bool => null !== $language && '' !== $language));
    }

    // Finds books based on search
    // "$serieId": the search a serie's own page carries, which looks inside that serie alone - the same field asks the whole catalog elsewhere (see book/index.html.twig)
    // A book replaced by a newer version is answered here, where no list shows it any more: the search and the page of the book replacing it are the two ways left to reach it (see Book::$newerVersion)
    public function search(string $query, ?int $serieId = null): array
    {
        if (empty($query)) {
            return [];
        }

        $builder = $this->createQueryBuilder('b')
            ->leftJoin('b.serie', 'serie')
        ;

        if (null !== $serieId) {
            $builder
                ->andWhere('b.serie = :serie')
                ->setParameter('serie', $serieId)
            ;
        }

        return $builder
            ->andWhere('b.title LIKE :query')
            ->andWhere('b.isDeleted = false')
            ->andWhere('b.hidden = false')
            ->andWhere('serie IS NULL OR serie.hidden = false')
            ->andWhere('b.published IS NOT NULL')
            ->andWhere('b.published <= :now')
            ->setParameter('now', new \DateTime())
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('b.published', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
}
