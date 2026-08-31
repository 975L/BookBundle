<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Repository;

use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Repository\ContributorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class ContributorRepositoryTest extends TestCase
{
    private string $dql = '';

    // The index holds whoever a reader can open something from: a book a newer version replaced credits nobody there, its own page being reached from the version replacing it
    public function testTheIndexLeavesOutSomeoneCreditedOnlyOnBooksAlreadyReplaced(): void
    {
        $this->createRepository()->findCredited();

        $this->assertStringContainsString('ab.isDeleted = false AND ab.hidden = false AND ab.newerVersion IS NULL', $this->dql);
        $this->assertStringContainsString('ib.isDeleted = false AND ib.hidden = false AND ib.newerVersion IS NULL', $this->dql);
    }

    // A person's page lists the series it lists the books: one trashed or set aside leaves it, its link answering 410 or 404
    public function testAPersonPageLeavesOutTheSeriesTrashedOrSetAside(): void
    {
        $this->createRepository()->findOneBySlugWithWorks('laurent-marquet');

        $this->assertStringContainsString('asr.isDeleted = false AND asr.hidden = false', $this->dql);
        $this->assertStringContainsString('isr.isDeleted = false AND isr.hidden = false', $this->dql);
    }

    // Each serie card shows its cover, so the medias travel with the series rather than a query per card (see Serie:Serie)
    public function testAPersonPageBringsTheCoversOfTheSeriesAlong(): void
    {
        $this->createRepository()->findOneBySlugWithWorks('laurent-marquet');

        $this->assertStringContainsString('LEFT JOIN asr.medias asrm', $this->dql);
        $this->assertStringContainsString('LEFT JOIN isr.medias isrm', $this->dql);
    }

    // The person is looked up whatever their state - their page answers 410 out of the trash and 404 set aside, which both need the row - where what credits them leaves them
    public function testThePersonThemselfIsLookedUpWhateverTheirState(): void
    {
        $this->createRepository()->findOneBySlugWithWorks('laurent-marquet');

        $this->assertStringNotContainsString('c.hidden', $this->dql);
        $this->assertStringNotContainsString('c.isDeleted', $this->dql);
    }

    // The query the repository builds is read back through the DQL the entity manager is handed, the rest of it being Doctrine's own
    private function createRepository(): ContributorRepository
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturnMap([
            [Contributor::class, new ClassMetadata(Contributor::class)],
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
            [Contributor::class, $entityManager],
        ]);

        return new ContributorRepository($registry);
    }
}
