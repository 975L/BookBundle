<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Command;

use c975L\BookBundle\Command\BookPrintablesRenameCommand;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookPrintable;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use c975L\BookBundle\Service\BookPrintableNamer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

// The documents uploaded before the site named them brought to their name, their thumbnail with them, only when asked
class BookPrintablesRenameCommandTest extends TestCase
{
    private const string OLD = 'medias/book/printables/item-1-6aecced7ef358';

    private const string NEW = 'medias/book/printables/activity-item-1-6aecced7ef358';

    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/book-printables-' . uniqid();
        new Filesystem()->dumpFile($this->projectDir . '/public/' . self::OLD . '.pdf', '%PDF');
        new Filesystem()->dumpFile($this->projectDir . '/public/' . self::OLD . '.webp', 'webp');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    private function tester(array $printables, EntityManagerInterface $em): CommandTester
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findAll')->willReturn($printables);
        $em->method('getRepository')->willReturn($repository);

        $registry = $this->createStub(BookCustomizationRegistry::class);
        $registry->method('getSections')->willReturn(['printables' => ['filename' => 'Activity']]);
        $registry->method('getPrintableKinds')->willReturn([]);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new CommandTester(new BookPrintablesRenameCommand($em, new BookPrintableNamer($registry, new AsciiSlugger(), $translator), $this->projectDir));
    }

    private static function printable(string $name): BookPrintable
    {
        $printable = new BookPrintable()->setBook(new Book()->setSlug('item-1'));
        $printable->setName($name);

        return $printable;
    }

    public function testWithoutForceItOnlyLists(): void
    {
        $printable = self::printable(self::OLD . '.pdf');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $tester = $this->tester([$printable], $em);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(self::OLD . '.pdf', $printable->getName());
        $this->assertFileExists($this->projectDir . '/public/' . self::OLD . '.pdf');
        $this->assertStringContainsString('1 document(s) à renommer', $tester->getDisplay());
    }

    public function testWithForceTheFileItsThumbnailAndItsRowAreRenamed(): void
    {
        $printable = self::printable(self::OLD . '.pdf');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $tester = $this->tester([$printable], $em);
        $tester->execute(['--force' => true]);

        $this->assertSame(self::NEW . '.pdf', $printable->getName());
        $this->assertFileExists($this->projectDir . '/public/' . self::NEW . '.pdf');
        $this->assertFileExists($this->projectDir . '/public/' . self::NEW . '.webp');
        $this->assertFileDoesNotExist($this->projectDir . '/public/' . self::OLD . '.pdf');
    }

    // Run a second time, nothing is left to do
    public function testADocumentAlreadyRenamedIsSkipped(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $tester = $this->tester([self::printable(self::NEW . '.pdf')], $em);
        $tester->execute(['--force' => true]);

        $this->assertStringContainsString('0 document(s) renommé(s)', $tester->getDisplay());
    }

    // A row whose file is gone is named for the editor rather than pointed at nothing
    public function testARowWhoseFileIsGoneIsReported(): void
    {
        $printable = self::printable('medias/book/printables/item-1-6aecced7ef999.pdf');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $tester = $this->tester([$printable], $em);
        $tester->execute(['--force' => true]);

        $this->assertSame('medias/book/printables/item-1-6aecced7ef999.pdf', $printable->getName());
        $this->assertStringContainsString('item-1-6aecced7ef999.pdf', $tester->getDisplay());
    }

    // A rename failing halfway leaves the rows already moved saved, never naming a file that is gone
    public function testTheRowsMovedBeforeAFailureAreSaved(): void
    {
        new Filesystem()->dumpFile($this->projectDir . '/public/medias/book/printables/item-1-6aecced7ef999.pdf', '%PDF');
        new Filesystem()->dumpFile($this->projectDir . '/public/medias/book/printables/activity-item-1-6aecced7ef999.pdf', 'taken');
        $printable = self::printable(self::OLD . '.pdf');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $tester = $this->tester([$printable, self::printable('medias/book/printables/item-1-6aecced7ef999.pdf')], $em);

        try {
            $tester->execute(['--force' => true]);
            $this->fail('The second rename should have failed.');
        } catch (IOException) {
            $this->assertSame(self::NEW . '.pdf', $printable->getName());
        }
    }
}
