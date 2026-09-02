<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Repository;

use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Repository\SerieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class SerieRepositoryTest extends TestCase
{
    private string $dql = '';

    // A serie's page lists what its catalog lists: a book a newer version replaces leaves it, as it left the trash-free listing
    public function testASeriePageLeavesOutABookAlreadyReplaced(): void
    {
        $this->createRepository()->findOneBySlugWithSortedBooks('histoires-de-contes-du-soir');

        $this->assertStringContainsString('b.isDeleted = false AND b.hidden = false AND b.newerVersion IS NULL', $this->dql);
    }

    // The list of series holds those a reader can open a book from: one whose books are all replaced offers nothing to browse
    public function testTheSeriesListingCountsOnlyBooksStillBrowsed(): void
    {
        $this->createRepository()->findWithBooks();

        $this->assertStringContainsString('b.isDeleted = false AND b.hidden = false AND b.newerVersion IS NULL', $this->dql);
    }

    // The two indexes split the series rather than each listing them all: what a serie declares decides which one holds it
    public function testTheBooksListingLeavesOutTheSeriesTellingPlanches(): void
    {
        $this->createRepository()->findWithBooks();

        $this->assertStringContainsString('(s.kind IS NULL OR s.kind = :kind)', $this->dql);
    }

    public function testThePlanchesListingLeavesOutTheSeriesTellingBooks(): void
    {
        $this->createRepository()->findWithStrips();

        $this->assertStringContainsString('(s.kind IS NULL OR s.kind = :kind)', $this->dql);
    }

    // The listing of planches holds series, not planches: what it joins on is only there to leave out a serie holding none
    public function testThePlanchesListingHoldsSeriesTellingAtLeastOne(): void
    {
        $this->createRepository()->findWithStrips();

        $this->assertStringContainsString('INNER JOIN s.strips st WITH st.isDeleted = false', $this->dql);
    }

    // A serie set aside leaves every public list at once - the front's, the sitemap's and the link picker's, all three reading this very method (see Entity\Trait\HideableTrait)
    public function testTheListingLeavesOutTheSeriesSetAside(): void
    {
        $this->createRepository()->findAll();

        $this->assertStringContainsString('s.hidden = false', $this->dql);
    }

    // On the join as well as on the serie: one whose every book is set aside heads a section with nothing under it
    public function testASerieHoldingOnlyBooksSetAsideIsNotListed(): void
    {
        $this->createRepository()->findWithBooks();

        $this->assertStringContainsString('s.hidden = false', $this->dql);
        $this->assertStringContainsString('b.hidden = false', $this->dql);
    }

    public function testASerieHoldingOnlyPlanchesSetAsideIsNotListed(): void
    {
        $this->createRepository()->findWithStrips();

        $this->assertStringContainsString('s.hidden = false', $this->dql);
        $this->assertStringContainsString('st.hidden = false', $this->dql);
    }

    // The serie itself is looked up whatever its state - its page answers 410 out of the trash and 404 set aside, which both need the row - where the books it lists leave it
    public function testASeriePageLeavesOutTheBooksSetAside(): void
    {
        $this->createRepository()->findOneBySlugWithSortedBooks('histoires-de-contes-du-soir');

        $this->assertStringContainsString('b.hidden = false', $this->dql);
        $this->assertStringNotContainsString('s.hidden', $this->dql);
    }

    // The query the repository builds is read back through the DQL the entity manager is handed, the rest of it being Doctrine's own
    private function createRepository(): SerieRepository
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturnMap([
            [Serie::class, new ClassMetadata(Serie::class)],
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
            [Serie::class, $entityManager],
        ]);

        return new SerieRepository($registry);
    }
}
