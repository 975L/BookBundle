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
use c975L\BookBundle\Entity\BookColoring;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Validation;

// A drawing to colour is a PNG, comes out on its day and keeps its crayons in order
class BookColoringTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'coloring');
    }

    protected function tearDown(): void
    {
        unlink($this->path);
    }

    private function violationsFor(string $contents): int
    {
        file_put_contents($this->path, $contents);
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        return \count($validator->validate(new BookColoring()->setFile(new File($this->path))));
    }

    public function testAPngIsAccepted(): void
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);

        $this->assertSame(0, $this->violationsFor((string) ob_get_clean()));
    }

    public function testAPdfIsRefused(): void
    {
        $this->assertSame(1, $this->violationsFor("%PDF-1.4\n%%EOF\n"));
    }

    // The add/remove pair the form writes through, by_reference being off
    public function testTheBookKeepsItsDrawingsInStepWithTheirOwner(): void
    {
        $book = new Book();
        $coloring = new BookColoring();

        $book->addColoring($coloring);
        $this->assertSame($book, $coloring->getBook());
        $this->assertCount(1, $book->getColorings());

        $book->removeColoring($coloring);
        $this->assertNull($coloring->getBook());
        $this->assertCount(0, $book->getColorings());
    }

    // What the app reads is only what is out today
    public function testTheBookOffersOnlyItsPublishedDrawings(): void
    {
        $out = new BookColoring();
        $later = new BookColoring()->setPublishedOn(new \DateTimeImmutable('+7 days'));
        $book = new Book()->addColoring($out)->addColoring($later);

        $this->assertSame([$out], $book->getPublishedColorings());
        $this->assertTrue(new BookColoring()->setPublishedOn(new \DateTimeImmutable('today'))->isPublished());
    }

    // No crayons read yet is an empty list, never null, and a list given with keys comes back as a list
    public function testThePaletteIsAlwaysAList(): void
    {
        $this->assertSame([], new BookColoring()->getPalette());
        $this->assertSame(['#ff0000', '#00ff00'], new BookColoring()->setPalette([3 => '#ff0000', 7 => '#00ff00'])->getPalette());
    }

    public function testItIsStoredUnderItsBook(): void
    {
        $this->assertSame('medias/book/colorings/item-1', new BookColoring()->setBook(new Book()->setSlug('item-1'))->getVichMediaPath());
        $this->assertSame('medias/book/colorings/temp', new BookColoring()->getVichMediaPath());
    }
}
