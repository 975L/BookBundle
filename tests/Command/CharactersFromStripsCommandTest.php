<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Command;

use c975L\BookBundle\Command\CharactersFromStripsCommand;
use c975L\BookBundle\Entity\Character;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Repository\SerieRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CharactersFromStripsCommandTest extends TestCase
{
    // The upgrade step of a site that had named its characters on every planche: one character per distinct name, and the planches pointing at them
    public function testEachDistinctNameBecomesOneCharacterTheStripsPointAt(): void
    {
        $serie = new Serie()->setSlug('la-tribu')->setTitle('La Tribu');
        $first = new Strip();
        $second = new Strip();

        $tester = $this->execute($serie, [$first, $second], [
            ['id' => 1, 'serie_id' => 8, 'names' => 'Papa, Loris'],
            ['id' => 2, 'serie_id' => 8, 'names' => 'Papa,Leïa'],
        ]);

        $this->assertStringContainsString('3 character(s) created', $tester->getDisplay());
        $this->assertStringContainsString('4 link(s) written', $tester->getDisplay());

        // Met once per planche it speaks in, held once on the serie
        $this->assertSame(['papa', 'loris', 'leia'], array_map(
            static fn (Character $character): string => (string) $character->getSlug(),
            $serie->getCharacters()->toArray()
        ));
        $this->assertSame(['papa', 'loris'], array_column($first->getCharactersList(), 'slug'));
        $this->assertSame(['papa', 'leia'], array_column($second->getCharactersList(), 'slug'));
    }

    // A serie that already presents its people is not given a second set of them: the cards were written before this ran
    public function testACharacterTheSerieAlreadyHoldsIsPointedAtRatherThanRemade(): void
    {
        $serie = new Serie()->setSlug('la-tribu')->setTitle('La Tribu');
        $papa = new Character()->setName('Papa')->setSlug('papa');
        $serie->addCharacter($papa);
        $strip = new Strip();

        $tester = $this->execute($serie, [$strip], [['id' => 1, 'serie_id' => 8, 'names' => 'Papa']]);

        $this->assertStringContainsString('0 character(s) created', $tester->getDisplay());
        $this->assertCount(1, $serie->getCharacters());
        $this->assertSame([$papa], $strip->getCharacters()->toArray());
    }

    // Run a second time, or on a site that never carried the column, it says so rather than failing on a column that is not there
    public function testItSaysThereIsNothingToDoWhereTheColumnIsGone(): void
    {
        $tester = $this->execute(new Serie(), [], [], hasColumn: false);

        $this->assertStringContainsString('Nothing to do', $tester->getDisplay());
    }

    /**
     * @param list<Strip>                $strips
     * @param list<array<string, mixed>> $rows
     */
    private function execute(Serie $serie, array $strips, array $rows, bool $hasColumn = true): CommandTester
    {
        $schemaManager = $this->createStub(AbstractSchemaManager::class);
        // Quoted as a real introspection names its columns, where toString() would keep the quotes
        $schemaManager->method('introspectTableColumnsByUnquotedName')->willReturn(
            $hasColumn ? [new Column('"characters"', Type::getType(Types::STRING))] : []
        );

        $connection = $this->createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);
        $connection->method('fetchAllAssociative')->willReturn($rows);

        $stripRepository = $this->createStub(EntityRepository::class);
        $stripRepository->method('find')->willReturnCallback(
            static fn (int $id): ?Strip => $strips[$id - 1] ?? null
        );

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($stripRepository);

        $serieRepository = $this->createStub(SerieRepository::class);
        $serieRepository->method('find')->willReturn($serie);

        $tester = new CommandTester(new CharactersFromStripsCommand($connection, $em, $serieRepository));
        $tester->execute([]);

        return $tester;
    }
}
