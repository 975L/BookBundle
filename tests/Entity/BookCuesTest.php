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
use c975L\BookBundle\Entity\BookMedia;
use PHPUnit\Framework\TestCase;

// A book's timecodes and its pages are collections of their own, filled by the field a file is dropped on
class BookCuesTest extends TestCase
{
    public function testABookWithoutTimecodesHasNone(): void
    {
        $book = new Book();
        $book->addPage(new BookMedia()->setName('page-1.webp'));

        $this->assertNull($book->getCues());
        $this->assertCount(0, $book->getCueFiles());
    }

    // The file dropped on the timecodes field becomes the book's cues, and never one of its pages
    public function testTheTimecodesFieldSaysWhatTheFileIs(): void
    {
        $book = new Book();
        $cues = new BookMedia()->setName('pages.vtt');
        $book->addCueFile($cues);
        $book->addPage(new BookMedia()->setName('page-1.webp'));

        $this->assertSame('cues', $cues->getKind());
        $this->assertSame($cues, $book->getCues());
        $this->assertCount(1, $book->getPages());
    }

    public function testRemovingTheFileLeavesTheBookWithoutTimecodes(): void
    {
        $book = new Book();
        $cues = new BookMedia()->setName('pages.vtt');
        $book->addCueFile($cues);
        $book->removeCueFile($cues);

        $this->assertNull($book->getCues());
    }
}
