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
use c975L\BookBundle\Entity\BookLink;
use c975L\BookBundle\Entity\BookMedia;
use PHPUnit\Framework\TestCase;

class BookEditionTest extends TestCase
{
    public function testAnEditionDatedInTheFutureIsNotOutYet(): void
    {
        $edition = new BookEdition()->setPublished(new \DateTime('+1 day'));

        $this->assertFalse($edition->isReleased());
    }

    public function testAnEditionWithNoDateIsNotOutEither(): void
    {
        $this->assertFalse(new BookEdition()->isReleased());
    }

    public function testAnEditionDatedInThePastIsOut(): void
    {
        $edition = new BookEdition()->setPublished(new \DateTime('-1 day'));

        $this->assertTrue($edition->isReleased());
    }

    // The book's own count stands for every edition holding as many pages as the text it comes from - a display fallback only, never written back to the edition's own column
    public function testAnEditionFallsBackOnTheBooksPageCount(): void
    {
        $book = new Book()->setPages(48);
        $edition = new BookEdition()->setBook($book);

        $this->assertNull($edition->getPages());
        $this->assertSame(48, $edition->getDisplayedPages());

        $edition->setPages(32);
        $this->assertSame(32, $edition->getPages());
        $this->assertSame(32, $edition->getDisplayedPages());
    }

    public function testABookReadsAnEditionByItsKind(): void
    {
        $book = new Book();
        $book->addEdition(new BookEdition()->setKind('audio')->setIsbn('9791092030143'));

        $this->assertSame('9791092030143', $book->getEdition('audio')?->getIsbn());
        $this->assertNull($book->getEdition('paper'));
    }

    // Out means any edition is out, a story whose illustrated version alone has appeared being read like any other
    public function testABookIsOutAsSoonAsOneOfItsEditionsIs(): void
    {
        $book = new Book();
        $book->addEdition(new BookEdition()->setKind('paper')->setPublished(new \DateTime('+1 year')));

        $this->assertFalse($book->isReleased());
        $this->assertCount(0, $book->getReleasedEditions());

        $book->addEdition(new BookEdition()->setKind('digital')->setPublished(new \DateTime('-1 day')));

        $this->assertTrue($book->isReleased());
        $this->assertCount(1, $book->getReleasedEditions());
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

    // The pages of the illustrated edition are not those of the original: a file belongs to the edition holding it, and the book only ever sees them all
    public function testAnEditionHoldsItsOwnFiles(): void
    {
        $book = new Book();
        $illustrated = new BookEdition()->setKind('illustrated_digital');
        $original = new BookEdition()->setKind('original_digital');
        $book->addEdition($illustrated);
        $book->addEdition($original);

        $illustrated->addMedia(new BookMedia()->setKind('page'));
        $illustrated->addMedia(new BookMedia()->setKind('flipbook'));
        $original->addMedia(new BookMedia()->setKind('page'));

        $this->assertCount(2, $illustrated->getMedias());
        $this->assertCount(1, $illustrated->getMedias('page'));
        $this->assertCount(3, $book->getMedias());
    }

    // The path a file is stored under is built on the book's slug, which a file added from its edition's own screen has to know (see BookMedia::getVichMediaPath())
    public function testAFileAddedToAnEditionKnowsTheBook(): void
    {
        $book = new Book();
        $edition = new BookEdition()->setKind('audio');
        $book->addEdition($edition);

        $media = new BookMedia()->setKind('audio_mp3');
        $edition->addMedia($media);

        $this->assertSame($edition, $media->getEdition());
        $this->assertSame($book, $media->getBook());
        $this->assertCount(1, $book->getMedias());
    }

    public function testAFileRemovedFromAnEditionLosesIt(): void
    {
        $edition = new BookEdition()->setKind('audio');
        $media = new BookMedia()->setKind('audio_mp3');
        $edition->addMedia($media);

        $edition->removeMedia($media);

        $this->assertCount(0, $edition->getMedias());
        $this->assertNull($media->getEdition());
    }

    // A platform sells one edition and not the book at large - the audiobook is on the podcast apps, the ebook in the stores
    public function testAnEditionHoldsTheLinksSellingIt(): void
    {
        $book = new Book();
        $audio = new BookEdition()->setKind('audio');
        $digital = new BookEdition()->setKind('illustrated_digital');
        $book->addEdition($audio);
        $book->addEdition($digital);

        $audio->addLink(new BookLink()->setKind('audio_gplay'));
        $digital->addLink(new BookLink()->setKind('epub_fnac'));

        $this->assertCount(1, $audio->getLinks());
        $this->assertSame('audio_gplay', $audio->getLinks()[0]->getKind());
        $this->assertSame($book, $audio->getLinks()[0]->getBook());
        $this->assertCount(2, $book->getLinks());
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
