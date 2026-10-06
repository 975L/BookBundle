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
use c975L\BookBundle\Entity\BookEdition;
use c975L\BookBundle\Entity\BookEditionFile;
use c975L\BookBundle\Enum\BookEditionFileKind;
use PHPUnit\Framework\TestCase;

class BookEditionFileTest extends TestCase
{
    // Kept out of public/, under a name telling the book and the kind of file
    public function testTheFileIsPrivateAndNamedAfterItsBookAndKind(): void
    {
        $book = new Book()->setSlug('le-loup');
        $file = new BookEditionFile();
        $book->addEdition(new BookEdition()->setKind('digital')->setFileOf(BookEditionFileKind::Epub, $file->setName('medias/book/editions/le-loup-epub.epub')));

        $this->assertSame('private', $file->getPrivateDirectory());
        $this->assertSame($book, $file->getBook());
        $this->assertSame('medias/book/editions/le-loup-epub', $file->getVichMediaPath());
    }

    // Uploaded before the edition hangs on a saved book, the name says so rather than failing
    public function testAFileWithoutBookIsNamedTemporarily(): void
    {
        $this->assertNull(new BookEditionFile()->getBook());
        $this->assertSame('medias/book/editions/temp-file', new BookEditionFile()->getVichMediaPath());
    }
}
