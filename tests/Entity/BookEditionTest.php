<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Entity;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookEdition;
use PHPUnit\Framework\TestCase;

class BookEditionTest extends TestCase
{
    // What a version is made of belongs to that version and to no other - the book itself counts no pages and names no format, a paperback never being the ebook of the same story
    public function testAnEditionCarriesItsOwnFormatAndPageCount(): void
    {
        $edition = new BookEdition();

        $this->assertNull($edition->getPages());
        $this->assertNull($edition->getFormat());

        $edition->setPages(32)->setFormat('15 x 21 cm');
        $this->assertSame(32, $edition->getPages());
        $this->assertSame('15 x 21 cm', $edition->getFormat());
    }

    public function testABookReadsAnEditionByItsKind(): void
    {
        $book = new Book();
        $book->addEdition(new BookEdition()->setKind('audio')->setIsbn('9791092030143'));

        $this->assertSame('9791092030143', $book->getEdition('audio')?->getIsbn());
        $this->assertNull($book->getEdition('paper'));
    }

    public function testAnEditionRemovedLosesItsBook(): void
    {
        $book = new Book();
        $edition = new BookEdition()->setKind('paper');
        $book->addEdition($edition);

        $book->removeEdition($edition);

        $this->assertCount(0, $book->getEditions());
        $this->assertNull($edition->getBook());
    }

    // The site's own fields, read by name so a template never spells out the payload's shape
    public function testABookReadsOneOfItsOwnFieldsByName(): void
    {
        $book = new Book()->setData(['idea' => 'Melvin, 5 ans']);

        $this->assertSame('Melvin, 5 ans', $book->getDataValue('idea'));
        $this->assertNull($book->getDataValue('absent'));
        $this->assertSame('-', $book->getDataValue('absent', '-'));
        $this->assertSame([], new Book()->getData());
    }
}
