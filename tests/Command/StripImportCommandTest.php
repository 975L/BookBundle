<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Command;

use c975L\BookBundle\Command\StripImportCommand;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Entity\StripMedia;
use c975L\BookBundle\Repository\SerieRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

// The one-shot import of an older site's strips: what it makes of a source row and of the directory of files standing beside it (see StripImportCommand)
class StripImportCommandTest extends TestCase
{
    private string $projectDir;

    /** @var list<object> */
    private array $persisted = [];

    private bool $flushed = false;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/book-strip-import-' . uniqid();
        mkdir($this->projectDir . '/assets/medias/tribu/001', 0o777, true);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    // The whole point of the command: a row of the old table becomes a strip, its transcription and its files with it
    public function testASourceRowBecomesAStripCarryingItsFiles(): void
    {
        $this->file('001/001.txt', "  La tribu, planche 1  \n");
        $this->file('001/001-page001.jpg');
        $this->file('001/001-Cases-page001.jpg');
        $this->file('001/001.pdf.webp');

        $tester = $this->import([['id' => 1, 'slug' => 'planche-1', 'title' => 'Planche 1', 'characters' => 'Papa,Alwin', 'published' => '2026-01-02']]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertTrue($this->flushed);

        $strip = $this->strips()[0];
        $this->assertSame('Planche 1', $strip->getTitle());
        $this->assertSame('planche-1', $strip->getSlug());
        $this->assertSame(1, $strip->getNumber());
        $this->assertSame('Papa,Alwin', $strip->getCharacters());
        $this->assertSame('La tribu, planche 1', $strip->getSummary());
        $this->assertSame('02/01/2026', $strip->getPublished()?->format('d/m/Y'));
    }

    // The three shapes a file can have, in the order the strip reads them - and their positions renumbered so the stored order is that one
    public function testTheFilesAreSortedThumbnailThenPageThenPanels(): void
    {
        $this->file('001/001-Cases-page002.jpg');
        $this->file('001/001-Cases-page001.jpg');
        $this->file('001/001-page001.jpg');
        $this->file('001/001.pdf.webp');

        $this->import([['id' => 1, 'slug' => 'planche-1', 'title' => 'Planche 1']]);

        $medias = $this->medias();
        $this->assertSame(['thumbnail', 'page', 'case', 'case'], array_map(static fn (StripMedia $media): ?string => $media->getKind(), $medias));
        $this->assertSame([0, 1, 2, 3], array_map(static fn (StripMedia $media): ?int => $media->getPosition(), $medias));
    }

    // The path stored is the public one, "assets/" dropped so asset() resolves it through AssetMapper
    public function testTheStoredPathIsThePublicOneRatherThanTheFilesystemOne(): void
    {
        $this->file('001/001-page001.jpg');

        $this->import([['id' => 1, 'slug' => 'planche-1', 'title' => 'Planche 1']]);

        $this->assertSame('medias/tribu/001/001-page001.jpg', $this->medias()[0]->getName());
    }

    // A file the naming rules say nothing about is left where it is rather than imported under a kind it has not
    public function testAFileNamedAnyOtherWayIsLeftOut(): void
    {
        $this->file('001/001-page001.jpg');
        $this->file('001/notes.txt');
        $this->file('001/cover.png');

        $this->import([['id' => 1, 'slug' => 'planche-1', 'title' => 'Planche 1']]);

        $this->assertCount(1, $this->medias());
    }

    // The address the strip was read at on the old site, rebuilt for the numbers it covered and left alone above them
    public function testTheSourceUrlIsBuiltUpToTheGivenNumberOnly(): void
    {
        $tester = $this->import(
            [
                ['id' => 1, 'slug' => 'planche-1', 'title' => 'Planche 1'],
                ['id' => 2, 'slug' => 'planche-2', 'title' => 'Planche 2'],
            ],
            ['--source-url-template' => 'https://contes-du-soir.example/replique/{number}', '--source-url-max' => '1']
        );

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame('https://contes-du-soir.example/replique/1', $this->strips()[0]->getSourceUrl());
        $this->assertNull($this->strips()[1]->getSourceUrl());
    }

    // The column an old table numbers its rows by is not always "id", and it is also the name of the directory the files sit in
    public function testAnotherColumnCanCarryTheNumber(): void
    {
        mkdir($this->projectDir . '/assets/medias/tribu/012');
        $this->file('012/012-page001.jpg');

        $this->import([['id' => 1, 'slug' => 'planche-12', 'title' => 'Planche 12', 'position' => 12]], ['--number-column' => 'position']);

        $this->assertSame(12, $this->strips()[0]->getNumber());
        $this->assertSame('medias/tribu/012/012-page001.jpg', $this->medias()[0]->getName());
    }

    // A row with no slug has no url to be read at, so it is reported and passed over rather than imported half-way
    public function testARowWithoutASlugIsSkipped(): void
    {
        $tester = $this->import([
            ['id' => 1, 'slug' => null, 'title' => 'Sans slug'],
            ['id' => 2, 'slug' => 'planche-2', 'title' => 'Planche 2'],
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertCount(1, $this->strips());
        $this->assertStringContainsString('1 strips created and flushed. 1 skipped.', $this->squash($tester->getDisplay()));
    }

    // What a dry run is for: the same report, and nothing written
    public function testADryRunWritesNothing(): void
    {
        $this->file('001/001-page001.jpg');

        $tester = $this->import([['id' => 1, 'slug' => 'planche-1', 'title' => 'Planche 1']], ['--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame([], $this->persisted);
        $this->assertFalse($this->flushed);
        $this->assertStringContainsString('1 strips would be created', $this->squash($tester->getDisplay()));
    }

    // An option left out is null, which no string function is to be handed
    public function testTheThreeRequiredOptionsAreAskedForBeforeAnythingElse(): void
    {
        $tester = $this->import([], ['--serie' => null, '--table' => null, '--media-dir' => null]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Options --serie, --table and --media-dir are all required.', $this->squash($tester->getDisplay()));
    }

    // The strips are linked to a serie, which has to be there to link them to
    public function testAnUnknownSerieStopsTheImport(): void
    {
        $tester = $this->import([], ['--serie' => 'nowhere'], serieFound: false);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString("No Serie found with slug 'nowhere'.", $this->squash($tester->getDisplay()));
    }

    // A directory typed wrong would import rows carrying no file at all, which is worse than not importing them
    public function testAMediaDirectoryThatIsNotThereStopsTheImport(): void
    {
        $tester = $this->import([], ['--media-dir' => 'assets/medias/elsewhere']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Directory not found', $this->squash($tester->getDisplay()));
    }

    // An empty table is said so rather than reported as a successful import of nothing
    public function testAnEmptyTableIsReportedAsSuch(): void
    {
        $tester = $this->import([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString("Table 'tribu' returned no rows.", $this->squash($tester->getDisplay()));
        $this->assertFalse($this->flushed);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, mixed>       $options
     */
    private function import(array $rows, array $options = [], bool $serieFound = true): CommandTester
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($rows);

        $serieRepository = $this->createStub(SerieRepository::class);
        $serieRepository->method('findOneBy')->willReturn($serieFound ? new Serie()->setTitle('La tribu') : null);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager
            ->method('persist')
            ->willReturnCallback(function (object $entity): void {
                $this->persisted[] = $entity;
            })
        ;
        $entityManager
            ->method('flush')
            ->willReturnCallback(function (): void {
                $this->flushed = true;
            })
        ;

        // A null option stands for one left out of the command line, which is not the same as one given empty
        $given = array_filter(array_merge([
            '--serie' => 'la-tribu',
            '--table' => 'tribu',
            '--media-dir' => 'assets/medias/tribu',
        ], $options), static fn (mixed $value): bool => null !== $value);

        $tester = new CommandTester(new StripImportCommand($connection, $entityManager, $serieRepository, $this->projectDir));
        $tester->execute($given);

        return $tester;
    }

    private function file(string $path, string $contents = 'x'): void
    {
        file_put_contents($this->projectDir . '/assets/medias/tribu/' . $path, $contents);
    }

    /** @return list<Strip> */
    private function strips(): array
    {
        return array_values(array_filter($this->persisted, static fn (object $entity): bool => $entity instanceof Strip));
    }

    /** @return list<StripMedia> */
    private function medias(): array
    {
        return array_values(array_filter($this->persisted, static fn (object $entity): bool => $entity instanceof StripMedia));
    }

    // The console wraps its report to the width of the terminal, which says nothing about what it reports
    private function squash(string $display): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $display));
    }
}
