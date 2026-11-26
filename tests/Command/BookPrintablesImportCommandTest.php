<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Command;

use c975L\BookBundle\Command\BookPrintablesImportCommand;
use c975L\BookBundle\Contract\BookCustomizationProviderInterface;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookPrintable;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// Documents laid on their books from a manifest, at a level the site names: uploaded once, known again by the bytes of their stored file, replaced in their slot when those change, written only when asked
class BookPrintablesImportCommandTest extends TestCase
{
    private const string PDF = "%PDF-1.4\nitem-1";

    private string $directory;

    private Book $book;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/book-printables-' . uniqid();
        new Filesystem()->dumpFile($this->directory . '/lot/Petits-Coloriages.pdf', self::PDF);
        $this->book = new Book()->setSlug('item-1');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    /** @param array<string, mixed> $manifest */
    private function import(array $manifest, bool $dryRun = false): CommandTester
    {
        file_put_contents($this->directory . '/lot/manifest.json', json_encode($manifest));

        $repository = $this->createStub(BookRepository::class);
        $repository->method('findOneByNumber')->willReturnCallback(fn (int $number): ?Book => 1 === $number ? $this->book : null);
        $repository->method('findOneBy')->willReturnCallback(fn (array $criteria): ?Book => 'item-1' === $criteria['slug'] ? $this->book : null);
        $provider = $this->createStub(BookCustomizationProviderInterface::class);
        $provider->method('getPrintableKinds')->willReturn(['small' => 'item-1', 'big' => 'item-2']);

        $command = new BookPrintablesImportCommand($this->createStub(EntityManagerInterface::class), $repository, new BookCustomizationRegistry([$provider], $this->createStub(TranslatorInterface::class)), $this->directory);
        $tester = new CommandTester($command);
        $tester->execute(['manifest' => $this->directory . '/lot/manifest.json', '--dry-run' => $dryRun]);

        return $tester;
    }

    // A document already stored under public/, holding the given bytes
    private function stored(string $bytes, string $kind = 'small', string $title = 'item-1'): BookPrintable
    {
        new Filesystem()->dumpFile($this->directory . '/public/medias/book/printables/item-1.pdf', $bytes);
        $printable = new BookPrintable()->setName('medias/book/printables/item-1.pdf')->setSize(\strlen($bytes))->setKind($kind)->setTitle($title)->setPublishedOn(new \DateTimeImmutable('2027-01-13'));
        $this->book->addPrintable($printable);

        return $printable;
    }

    // Uploaded at the end of its book's documents, with its title, level and day
    public function testADocumentIsLaidOnItsBook(): void
    {
        $tester = $this->import(['printables' => [['book' => 1, 'file' => 'Petits-Coloriages.pdf', 'title' => 'item-1', 'kind' => 'small', 'publishedOn' => '2027-01-13']]]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $printable = $this->book->getPrintables()->first();
        $this->assertInstanceOf(BookPrintable::class, $printable);
        $this->assertInstanceOf(ReplacingFile::class, $printable->getFile());
        $this->assertSame(1, $printable->getPosition());
        $this->assertSame('item-1', $printable->getTitle());
        $this->assertSame('small', $printable->getKind());
        $this->assertSame('2027-01-13', $printable->getPublishedOn()?->format('Y-m-d'));
    }

    // Run again, the document is known by its stored bytes: nothing uploaded, the day it carries kept when the manifest names none
    public function testADocumentAlreadyThereIsNotUploadedAgain(): void
    {
        $printable = $this->stored(self::PDF, 'small', 'item-2');

        $tester = $this->import(['printables' => [['book' => 'item-1', 'file' => 'Petits-Coloriages.pdf', 'title' => 'item-1']]]);

        $this->assertCount(1, $this->book->getPrintables());
        $this->assertNull($printable->getFile());
        $this->assertSame('item-1', $printable->getTitle());
        $this->assertSame('2027-01-13', $printable->getPublishedOn()?->format('Y-m-d'));
        $this->assertStringContainsString('= item-1', $tester->getDisplay());
    }

    // New bytes at the same level under the same title take the old document's place rather than adding a second one
    public function testADocumentWhoseBytesChangedIsReplacedInItsSlot(): void
    {
        $printable = $this->stored("%PDF-1.4\nitem-2");

        $tester = $this->import(['printables' => [['book' => 1, 'file' => 'Petits-Coloriages.pdf', 'title' => 'item-1', 'kind' => 'small']]]);

        $this->assertCount(1, $this->book->getPrintables());
        $this->assertInstanceOf(ReplacingFile::class, $printable->getFile());
        $this->assertStringContainsString('~ item-1', $tester->getDisplay());
    }

    // The same bytes at another level are another document
    public function testAnotherLevelIsAnotherDocument(): void
    {
        $this->stored("%PDF-1.4\nitem-2", 'big');

        $this->import(['printables' => [['book' => 1, 'file' => 'Petits-Coloriages.pdf', 'title' => 'item-1', 'kind' => 'small']]]);

        $this->assertCount(2, $this->book->getPrintables());
        $this->assertSame(2, $this->book->getPrintables()->last()->getPosition());
    }

    // Only a day written counts: a null leaves a day still ahead where it is
    public function testANullDayKeepsTheDayAhead(): void
    {
        $printable = $this->stored(self::PDF);

        $this->import(['printables' => [['book' => 1, 'file' => 'Petits-Coloriages.pdf', 'publishedOn' => null]]]);

        $this->assertSame('2027-01-13', $printable->getPublishedOn()?->format('Y-m-d'));
    }

    // Two new documents sharing a title and a level in one manifest are two documents, the second never replacing the first
    public function testTwoNewDocumentsOfOneSlotAreBothLaid(): void
    {
        new Filesystem()->dumpFile($this->directory . '/lot/Grands-Coloriages.pdf', "%PDF-1.4\nitem-2");

        $this->import(['printables' => [
            ['book' => 1, 'file' => 'Petits-Coloriages.pdf', 'title' => 'item-1', 'kind' => 'small'],
            ['book' => 1, 'file' => 'Grands-Coloriages.pdf', 'title' => 'item-1', 'kind' => 'small'],
        ]]);

        $this->assertCount(2, $this->book->getPrintables());
    }

    public function testADryRunWritesNothing(): void
    {
        $tester = $this->import(['printables' => [['book' => 1, 'file' => 'Petits-Coloriages.pdf', 'kind' => 'big']]], true);

        $this->assertCount(0, $this->book->getPrintables());
        $this->assertStringContainsString('+ item-1 : Petits-Coloriages.pdf, aussitôt, big', $tester->getDisplay());
    }

    // A book, a file or a level not found, a file that is no PDF or one out of the manifest's folder, and a day misspelt are named, the others written
    public function testWhatCannotBeLaidIsNamed(): void
    {
        new Filesystem()->dumpFile($this->directory . '/lot/item-2.pdf', '<?php echo 1;');
        new Filesystem()->dumpFile($this->directory . '/outside.pdf', self::PDF);
        new Filesystem()->dumpFile($this->directory . '/lot/item-3.html', self::PDF);

        $tester = $this->import(['printables' => [
            ['book' => 2, 'file' => 'Petits-Coloriages.pdf'],
            ['book' => 1, 'file' => 'missing.pdf'],
            ['book' => 1, 'file' => 'item-2.pdf'],
            ['book' => 1, 'file' => '../outside.pdf'],
            ['book' => 1, 'file' => 'item-3.html'],
            ['book' => 1, 'file' => 'Petits-Coloriages.pdf', 'kind' => 'item-3'],
            ['book' => 1, 'file' => 'Petits-Coloriages.pdf', 'publishedOn' => 'item-1'],
            ['book' => 1, 'file' => 'Petits-Coloriages.pdf', 'kind' => 'small'],
        ]]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertCount(1, $this->book->getPrintables());
        $this->assertStringContainsString('missing.pdf', $tester->getDisplay());
    }

    public function testAnUnreadableManifestFails(): void
    {
        $this->assertSame(Command::FAILURE, $this->import(['colorings' => []])->getStatusCode());
    }
}
