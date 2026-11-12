<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Command;

use c975L\BookBundle\Command\BookColoringsImportCommand;
use c975L\BookBundle\Contract\BookCustomizationProviderInterface;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookColoring;
use c975L\BookBundle\Entity\BookPrintable;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Service\BookColoringPalette;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

// Drawings laid on their books from a manifest, at a level the site names: out with the book's documents to print and never before its first day, read again without uploading twice, written only when asked
class BookColoringsImportCommandTest extends TestCase
{
    private string $directory;

    private Book $book;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/book-colorings-' . uniqid();
        new Filesystem()->dumpFile($this->directory . '/Ecran/item-1.png', 'drawing');
        $image = imagecreatetruecolor(10, 10);
        imagefilledrectangle($image, 0, 0, 9, 9, (int) imagecolorallocate($image, 200, 30, 30));
        imagepng($image, $this->directory . '/source.png');

        $this->book = new Book()->setSlug('item-1');
        $this->book->addPrintable(new BookPrintable()->setPublishedOn(new \DateTimeImmutable('2027-01-13')));
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    // The command over the given books, on a site naming the levels "small" and "big"
    private function command(BookRepository $repository): BookColoringsImportCommand
    {
        $provider = $this->createStub(BookCustomizationProviderInterface::class);
        $provider->method('getPrintableKinds')->willReturn(['small' => 'item-1', 'big' => 'item-2']);

        return new BookColoringsImportCommand($this->createStub(EntityManagerInterface::class), $repository, new BookColoringPalette(), new BookCustomizationRegistry([$provider], $this->createStub(TranslatorInterface::class)));
    }

    /** @param array<string, mixed> $manifest */
    private function import(array $manifest, bool $dryRun = false): CommandTester
    {
        file_put_contents($this->directory . '/manifest.json', json_encode($manifest));

        $repository = $this->createStub(BookRepository::class);
        $repository->method('findOneByNumber')->willReturnCallback(fn (int $number): ?Book => 1 === $number ? $this->book : null);
        $repository->method('findOneBy')->willReturnCallback(fn (array $criteria): ?Book => 'item-1' === $criteria['slug'] ? $this->book : null);

        $tester = new CommandTester($this->command($repository));
        $tester->execute(['manifest' => $this->directory . '/manifest.json', '--dry-run' => $dryRun]);

        return $tester;
    }

    // Out with the book's last document to print, its crayons read off the illustration, at the level the manifest gives
    public function testADrawingComesOutWithItsBooksDocuments(): void
    {
        $tester = $this->import(['notBefore' => '2027-01-06', 'colorings' => [['book' => 1, 'file' => 'Ecran/item-1.png', 'source' => 'source.png', 'title' => 'item-1', 'kind' => 'small']]]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $coloring = $this->book->getColorings()->first();
        $this->assertInstanceOf(BookColoring::class, $coloring);
        $this->assertSame('2027-01-13', $coloring->getPublishedOn()?->format('Y-m-d'));
        $this->assertSame('item-1', $coloring->getTitle());
        $this->assertSame('small', $coloring->getKind());
        $this->assertSame(BookColoringPalette::FIXED, \array_slice($coloring->getPalette(), -3));
    }

    // A book whose documents came out long ago waits for the first day all the same
    public function testNothingComesOutBeforeTheFirstDay(): void
    {
        $this->import(['notBefore' => '2027-02-01', 'colorings' => [['book' => 'item-1', 'file' => 'Ecran/item-1.png']]]);

        $this->assertSame('2027-02-01', $this->book->getColorings()->first()->getPublishedOn()?->format('Y-m-d'));
    }

    // Run again, the drawing is known by its fingerprint: its day changes, no second row
    public function testADrawingAlreadyThereIsNotUploadedAgain(): void
    {
        $this->book->addColoring(new BookColoring()->setFingerprint(hash('xxh128', 'drawing')));

        $this->import(['colorings' => [['book' => 1, 'file' => 'Ecran/item-1.png', 'publishedOn' => '2027-03-03']]]);

        $this->assertCount(1, $this->book->getColorings());
        $this->assertSame('2027-03-03', $this->book->getColorings()->first()->getPublishedOn()?->format('Y-m-d'));
    }

    public function testADryRunWritesNothing(): void
    {
        $tester = $this->import(['colorings' => [['book' => 1, 'file' => 'Ecran/item-1.png', 'kind' => 'big']]], true);

        $this->assertCount(0, $this->book->getColorings());
        $this->assertStringContainsString('item-1.png', $tester->getDisplay());
        $this->assertStringContainsString('big', $tester->getDisplay());
    }

    // A level the site doesn't name leaves its drawing out and names it, the others written
    public function testAnUnknownLevelIsNamed(): void
    {
        $tester = $this->import(['colorings' => [['book' => 1, 'file' => 'Ecran/item-1.png', 'kind' => 'item-3'], ['book' => 1, 'file' => 'Ecran/item-1.png', 'kind' => 'small']]]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertCount(1, $this->book->getColorings());
        $coloring = $this->book->getColorings()->first();
        $this->assertInstanceOf(BookColoring::class, $coloring);
        $this->assertSame('small', $coloring->getKind());
        $this->assertStringContainsString('niveau inconnu', $tester->getDisplay());
    }

    // A book or a file not found is named, the others written
    public function testWhatIsNotFoundIsNamed(): void
    {
        $tester = $this->import(['colorings' => [['book' => 2, 'file' => 'Ecran/item-1.png'], ['book' => 1, 'file' => 'missing.png'], ['book' => 1, 'file' => 'Ecran/item-1.png']]]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertCount(1, $this->book->getColorings());
        $this->assertStringContainsString('missing.png', $tester->getDisplay());
    }

    // A path out of the manifest's folder or a file other than a PNG is left out like a missing one
    public function testAFileOutsideTheManifestOrNotAPngIsLeftOut(): void
    {
        new Filesystem()->dumpFile($this->directory . '/Ecran/item-2.php', 'script');
        new Filesystem()->dumpFile(\dirname($this->directory) . '/outside-' . basename($this->directory) . '.png', 'drawing');

        $tester = $this->import(['colorings' => [
            ['book' => 1, 'file' => '../outside-' . basename($this->directory) . '.png'],
            ['book' => 1, 'file' => 'Ecran/item-2.php'],
        ]]);
        new Filesystem()->remove(\dirname($this->directory) . '/outside-' . basename($this->directory) . '.png');

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertCount(0, $this->book->getColorings());
    }

    // A day misspelt leaves its drawing out and names it, the others written
    public function testAMisspeltDayIsNamed(): void
    {
        $tester = $this->import(['colorings' => [['book' => 1, 'file' => 'Ecran/item-1.png', 'publishedOn' => 'item-1'], ['book' => 'item-1', 'file' => 'Ecran/item-1.png']]]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertCount(1, $this->book->getColorings());
        $this->assertStringContainsString('date illisible', $tester->getDisplay());
    }

    public function testAMisspeltFirstDayFails(): void
    {
        $tester = $this->import(['notBefore' => 'item-1', 'colorings' => []]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertCount(0, $this->book->getColorings());
    }

    public function testAnUnreadableManifestFails(): void
    {
        file_put_contents($this->directory . '/manifest.json', '{');
        $tester = new CommandTester($this->command($this->createStub(BookRepository::class)));

        $this->assertSame(Command::FAILURE, $tester->execute(['manifest' => $this->directory . '/manifest.json']));
    }
}
