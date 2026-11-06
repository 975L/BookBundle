<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Entity;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookPrintable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Validation;

// A document to print is a PDF: nothing else has a thumbnail cut from it, and a page offering an image to print would print it at the size of a thumbnail
class BookPrintableTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'printable');
    }

    protected function tearDown(): void
    {
        unlink($this->path);
    }

    private function violationsFor(string $contents): int
    {
        file_put_contents($this->path, $contents);
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        return \count($validator->validate(new BookPrintable()->setFile(new File($this->path))));
    }

    public function testAPdfIsAccepted(): void
    {
        $this->assertSame(0, $this->violationsFor("%PDF-1.4\n%%EOF\n"));
    }

    public function testAnImageIsRefused(): void
    {
        $this->assertSame(1, $this->violationsFor("\x89PNG\r\n\x1a\n" . str_repeat("\0", 32)));
    }

    // The add/remove pair the form writes through, by_reference being off
    public function testTheBookKeepsItsDocumentsInStepWithTheirOwner(): void
    {
        $book = new Book();
        $printable = new BookPrintable();

        $book->addPrintable($printable);
        $this->assertSame($book, $printable->getBook());
        $this->assertCount(1, $book->getPrintables());

        $book->removePrintable($printable);
        $this->assertNull($printable->getBook());
        $this->assertCount(0, $book->getPrintables());
    }

    // Out the day it is dated, whatever the hour, and at once when it has no day
    public function testItIsPublishedFromItsDayOn(): void
    {
        $this->assertTrue(new BookPrintable()->isPublished());
        $this->assertTrue(new BookPrintable()->setPublishedOn(new \DateTimeImmutable('today'))->isPublished());
        $this->assertTrue(new BookPrintable()->setPublishedOn(new \DateTimeImmutable('yesterday'))->isPublished());
        $this->assertFalse(new BookPrintable()->setPublishedOn(new \DateTimeImmutable('tomorrow'))->isPublished());
    }

    // The book's page and the listing read only what is out today, in the order the book holds them
    public function testTheBookOffersOnlyItsPublishedDocuments(): void
    {
        $out = new BookPrintable();
        $later = new BookPrintable()->setPublishedOn(new \DateTimeImmutable('+7 days'));
        $today = new BookPrintable()->setPublishedOn(new \DateTimeImmutable('today'));
        $book = new Book()->addPrintable($out)->addPrintable($later)->addPrintable($today);

        $this->assertSame([$out, $today], $book->getPublishedPrintables());
        $this->assertSame([], new Book()->addPrintable($later)->getPublishedPrintables());
    }

    // Under a directory of their own, the slug naming the book the way every other file a book owns does
    public function testItsFilesAreStoredUnderTheBooksSlug(): void
    {
        $printable = new BookPrintable()->setBook(new Book()->setSlug('contes-du-soir'));

        $this->assertSame('medias/book/printables/contes-du-soir', $printable->getVichMediaPath());
    }

    // The words the site names its documents by come before the book's slug
    public function testItsPrefixComesBeforeTheBooksSlug(): void
    {
        $printable = new BookPrintable()->setBook(new Book()->setSlug('contes-du-soir'))->setNamePrefix('activity-beginners');

        $this->assertSame('medias/book/printables/activity-beginners-contes-du-soir', $printable->getVichMediaPath());
    }
}
