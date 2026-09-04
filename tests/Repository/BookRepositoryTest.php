<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Repository;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class BookRepositoryTest extends TestCase
{
    private string $dql = '';

    /** Every DQL the repository handed over, in order: what a listing costing a second query is read on @var list<string> */
    private array $dqls = [];

    /** What the next query answers, the ones after it answering nothing @var list<list<Book>> */
    private array $results = [];

    // The catalog lists what a reader browses: a book a newer version replaces is not one of them
    public function testTheCatalogLeavesOutABookAlreadyReplaced(): void
    {
        $this->createRepository()->findAllPublished();

        $this->assertStringContainsString('b.newerVersion IS NULL', $this->dql);
    }

    // A book set aside leaves the catalog, the search and the sitemap alike, and comes back to all three the moment the box is unticked (see Entity\Trait\HideableTrait)
    public function testTheCatalogLeavesOutABookSetAside(): void
    {
        $this->createRepository()->findAllPublished();

        $this->assertStringContainsString('b.hidden = false', $this->dql);
    }

    // A listing narrowed down to a category reads the very same catalog, with one join more - and skips a category set aside, whose page answers 404 anyway
    public function testTheCatalogOfACategoryKeepsEveryRuleOfTheWholeCatalog(): void
    {
        $this->createRepository()->findPublishedByCategory('romans');

        $this->assertStringContainsString('b.hidden = false', $this->dql);
        $this->assertStringContainsString('b.newerVersion IS NULL', $this->dql);
        $this->assertStringContainsString('category.slug = :categorySlug', $this->dql);
        $this->assertStringContainsString('category.hidden = false', $this->dql);
        // The rule the whole catalog reads: a book of a serie set aside is no more shown under a category than it is on the catalog's own index
        $this->assertStringContainsString('serie.hidden = false', $this->dql);
    }

    public function testTheSitemapLeavesOutABookSetAside(): void
    {
        $this->createRepository()->findAllOnline();

        $this->assertStringContainsString('b.hidden = false', $this->dql);
    }

    // A book of a serie set aside is no more shown than the serie telling it, and the filtering is read here rather than guarded on each of the ways a row is written
    public function testTheCatalogLeavesOutABookOfASerieSetAside(): void
    {
        $this->createRepository()->findAllPublished();

        $this->assertStringContainsString('serie IS NULL OR serie.hidden = false', $this->dql);
    }

    // The block announcing what is coming is a public read like any other: it was the only one left without the pair
    public function testTheBooksToComeLeaveOutWhatIsSetAside(): void
    {
        $this->createRepository()->findAllToBePublished();

        $this->assertStringContainsString('b.hidden = false', $this->dql);
        $this->assertStringContainsString('serie IS NULL OR serie.hidden = false', $this->dql);
    }

    // Unlike a replaced book, which the search still answers: a book set aside is reachable nowhere at all as long as it is
    public function testTheSearchLeavesOutABookSetAside(): void
    {
        $this->createRepository()->search('câlin');

        $this->assertStringContainsString('b.hidden = false', $this->dql);
    }

    // The numbered url and the short link both land here, and neither may lead to a book that is off the site
    public function testANumberNeverLeadsToABookSetAside(): void
    {
        $this->createRepository()->findOneByNumber(3);

        $this->assertStringContainsString('b.hidden = false', $this->dql);
    }

    // The search is one of the two ways left to reach a replaced book, the page of the book replacing it being the other: it answers what no list shows any more
    public function testTheSearchAnswersABookAlreadyReplaced(): void
    {
        $this->createRepository()->search('câlin');

        $this->assertStringNotContainsString('newerVersion', $this->dql);
    }

    // A book in the trash or not published yet is off the site, replaced or not: the search says so too
    public function testTheSearchLeavesOutWhatTheSiteDoesNotServe(): void
    {
        $this->createRepository()->search('câlin');

        $this->assertStringContainsString('b.isDeleted = false', $this->dql);
        $this->assertStringContainsString('b.published IS NOT NULL', $this->dql);
        $this->assertStringContainsString('b.published <= :now', $this->dql);
    }

    // A numbered url has to land on what the catalog reads today: two rows carry the same number - a version and the one replacing it - and the query says which
    public function testANumberLeadsToTheBookStillListed(): void
    {
        $this->createRepository()->findOneByNumber(3);

        $this->assertStringContainsString('b.number = :number', $this->dql);
        $this->assertStringContainsString('b.newerVersion IS NULL', $this->dql);
        // Nor a book in the trash, which would lead to a 404 while the catalog's own would never be reached
        $this->assertStringContainsString('b.isDeleted = false', $this->dql);
    }

    // Two books left sharing a number answer in a fixed order rather than in the one the database happens to return
    public function testANumberAnswersTheSameBookAtEveryCall(): void
    {
        $this->createRepository()->findOneByNumber(3);

        $this->assertStringContainsString('ORDER BY b.id ASC', $this->dql);
    }

    // The exact complement of what the listings answer: the veto letting a block be cached at all hangs on it (see BookBlockCacheTagProvider), and a book dated ahead is the one case an entry would outlive
    public function testAScheduledPublicationIsReadAsTheListingsReadTheirDate(): void
    {
        $this->createRepository()->hasScheduled();

        $this->assertStringContainsString('b.published > :now', $this->dql);
    }

    // One row is enough to answer, where reading the whole catalog would cost a query per block
    public function testAScheduledPublicationIsLookedForOnASingleRow(): void
    {
        $repository = $this->createRepository();

        $this->assertFalse($repository->hasScheduled());
        $this->assertStringContainsString('b.hidden = false', $this->dql);
        $this->assertStringContainsString('b.isDeleted = false', $this->dql);
    }

    // An inverse one-to-one cannot be a lazy proxy - it has to be read to be known absent - so a listing built without it costs one query per book (see Book::$previousVersion). A to-one join, which multiplies no row and survives the setMaxResults() the catalog adds
    public function testTheCatalogReadsTheBookItReplacesInTheSameQuery(): void
    {
        $this->createRepository()->findAllPublished(6);

        $this->assertStringContainsString('LEFT JOIN b.previousVersion previousVersion', $this->dql);
        $this->assertStringContainsString('SELECT b, previousVersion', $this->dql);
    }

    // The same on the block announcing what is coming, which lists books the same way
    public function testTheBooksToComeReadTheBookTheyReplaceInTheSameQuery(): void
    {
        $this->createRepository()->findAllToBePublished();

        $this->assertStringContainsString('LEFT JOIN b.previousVersion previousVersion', $this->dqls[0]);
        $this->assertStringContainsString('SELECT b, previousVersion', $this->dqls[0]);
    }

    // The covers a listing of cards reads, in one query for the whole list: the collection cannot be joined into the listing itself, whose setMaxResults() would then cut rows and not books
    public function testTheCoversOfAListingAreReadInOneQueryForTheWholeList(): void
    {
        $repository = $this->createRepository();
        $this->results = [[$this->book(1), $this->book(2)]];

        $repository->findAllPublished(6);

        $this->assertCount(2, $this->dqls);
        $this->assertStringContainsString('LEFT JOIN b.medias medias', $this->dql);
        $this->assertStringContainsString('b.id IN (:ids)', $this->dql);
    }

    // Nothing listed, nothing to read: a page whose catalog is empty asks for no cover at all
    public function testAnEmptyListingAsksForNoCoverAtAll(): void
    {
        $this->createRepository()->findAllPublished(6);

        $this->assertCount(1, $this->dqls);
    }

    // A book as the listing hands it over: only its id is read, the covers query being built on that alone
    private function book(int $id): Book
    {
        $book = new Book();
        new \ReflectionProperty(Book::class, 'id')->setValue($book, $id);

        return $book;
    }

    // The query the repository builds is read back through the DQL the entity manager is handed, the rest of it being Doctrine's own
    private function createRepository(): BookRepository
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturnMap([
            [Book::class, new ClassMetadata(Book::class)],
        ]);
        $entityManager->method('createQueryBuilder')->willReturnCallback(fn (): QueryBuilder => new QueryBuilder($entityManager));
        $entityManager->method('createQuery')->willReturnCallback(function (string $dql): Query {
            $this->dql = $dql;
            $this->dqls[] = $dql;

            $query = $this->createStub(Query::class);
            $query->method('setParameters')->willReturnSelf();
            $query->method('setFirstResult')->willReturnSelf();
            $query->method('setMaxResults')->willReturnSelf();
            $query->method('getResult')->willReturn(array_shift($this->results) ?? []);
            $query->method('getOneOrNullResult')->willReturn(null);

            return $query;
        });

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturnMap([
            [Book::class, $entityManager],
        ]);

        return new BookRepository($registry);
    }
}
