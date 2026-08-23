<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Repository;

use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Repository\StripRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class StripRepositoryTest extends TestCase
{
    private string $dql = '';

    /**
     * @var array<string, mixed>
     */
    private array $parameters = [];

    // A visitor types words, not plate numbers: a title numbering the plate ("Réplique 042") answers nothing, so the three fields carrying words are the three searched
    public function testSearchReadsTheTitleTheCharactersAndTheSummary(): void
    {
        $this->createRepository()->search('poulet');

        $this->assertStringContainsString('s.title LIKE :query', $this->dql);
        $this->assertStringContainsString('s.characters LIKE :query', $this->dql);
        $this->assertStringContainsString('s.summary LIKE :query', $this->dql);
    }

    // Surrounded by wildcards: what is looked for is a word inside a line of dialogue, never the whole of it
    public function testSearchLooksForTheTermAnywhereInTheField(): void
    {
        $this->createRepository()->search('poulet');

        $this->assertSame('%poulet%', $this->parameters['query'] ?? null);
    }

    // A planche in the trash, one never published and one published later today are all off the site: the search answers exactly what the listing shows
    public function testSearchLeavesOutWhatTheSiteDoesNotServe(): void
    {
        $this->createRepository()->search('poulet');

        $this->assertStringContainsString('s.isDeleted = false', $this->dql);
        $this->assertStringContainsString('s.published IS NOT NULL', $this->dql);
        $this->assertStringContainsString('s.published <= :now', $this->dql);
    }

    // The newest first, as everywhere else the planches are listed - and two of the same day on their id, the very tie the navigation from one planche to the next is settled on
    public function testSearchOrdersByPublicationDateDescendingThenById(): void
    {
        $this->createRepository()->search('poulet');

        $this->assertStringContainsString('ORDER BY s.published DESC, s.id DESC', $this->dql);
    }

    // An empty term matches every row through "%%": the query is not run at all rather than answering the whole catalogue
    public function testSearchRunsNoQueryOnAnEmptyTerm(): void
    {
        $this->assertSame([], $this->createRepository()->search(''));
        $this->assertSame('', $this->dql);
    }

    // The number a short link and a numbered url both hand over leads to the planche carrying it
    public function testANumberLeadsToThePlancheCarryingIt(): void
    {
        $this->createRepository()->findOneByNumber(158);

        $this->assertStringContainsString('s.number = :number', $this->dql);
        $this->assertStringContainsString('ORDER BY s.id ASC', $this->dql);
        // What the catalog reads today, never a draft nor a planche in the trash
        $this->assertStringContainsString('s.isDeleted = false', $this->dql);
        $this->assertStringContainsString('s.published IS NOT NULL', $this->dql);
        $this->assertStringContainsString('s.published <= :now', $this->dql);
    }

    // The query the repository builds is read back through the DQL the entity manager is handed, the rest of it being Doctrine's own
    private function createRepository(): StripRepository
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturnMap([
            [Strip::class, new ClassMetadata(Strip::class)],
        ]);
        $entityManager->method('createQueryBuilder')->willReturnCallback(fn (): QueryBuilder => new QueryBuilder($entityManager));
        $entityManager->method('createQuery')->willReturnCallback(function (string $dql): Query {
            $this->dql = $dql;

            $query = $this->createStub(Query::class);
            $query->method('setParameters')->willReturnCallback(function (mixed $parameters) use ($query): Query {
                foreach ($parameters as $parameter) {
                    $this->parameters[$parameter->getName()] = $parameter->getValue();
                }

                return $query;
            });
            $query->method('setFirstResult')->willReturnSelf();
            $query->method('setMaxResults')->willReturnSelf();
            $query->method('getResult')->willReturn([]);
            $query->method('getOneOrNullResult')->willReturn(null);

            return $query;
        });

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturnMap([
            [Strip::class, $entityManager],
        ]);

        return new StripRepository($registry);
    }

    // The search a serie's own page carries asks that serie alone, where the listing of every planche asks the whole site
    public function testSearchHeldInsideASerieOnlyLooksThere(): void
    {
        $this->createRepository()->search('poulet', 3);

        $this->assertStringContainsString('s.serie = :serie', $this->dql);
    }

    public function testSearchWithoutASerieLooksEverywhere(): void
    {
        $this->createRepository()->search('poulet');

        $this->assertStringNotContainsString('s.serie', $this->dql);
    }
}
