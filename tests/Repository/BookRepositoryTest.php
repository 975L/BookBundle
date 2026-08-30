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

            $query = $this->createStub(Query::class);
            $query->method('setParameters')->willReturnSelf();
            $query->method('setFirstResult')->willReturnSelf();
            $query->method('setMaxResults')->willReturnSelf();
            $query->method('getResult')->willReturn([]);
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
