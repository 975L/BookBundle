<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Entity;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Twig\BookSectionsExtension;
use PHPUnit\Framework\TestCase;

// The three images a book carries as a whole, each uploaded on a field of its own (see BookCrudController): the field is what says which of the three a file is, so a site never has to name any of them in its own vocabulary
class BookCoversTest extends TestCase
{
    public function testTheFieldAFileIsDroppedOnSaysWhatItIs(): void
    {
        $book = new Book();
        $book->addCover(new BookMedia()->setName('cover.webp'));
        $book->addBackCover(new BookMedia()->setName('back.webp'));
        $book->addBackground(new BookMedia()->setName('bg.webp'));

        $this->assertSame('cover.webp', (string) $book->getCovers()->first());
        $this->assertSame('back.webp', (string) $book->getBackCovers()->first());
        $this->assertSame('bg.webp', (string) $book->getBackgrounds()->first());
    }

    // What stands for the book wherever it is named - its page, its card, the social card built on it - is the cover uploaded on the field of its own, whatever else the catalog holds first
    public function testTheCoverBeatsTheFirstImageOfTheCatalog(): void
    {
        $book = new Book();
        $book->addMedia(new BookMedia()->setName('page.webp')->setKind('extract'));
        $book->addCover(new BookMedia()->setName('cover.webp'));

        $this->assertSame('cover.webp', (string) BookSectionsExtension::cover($book));
    }

    // For want of an image of its own, the book shows the one of another version of the same text: a newly illustrated version whose pages are not uploaded yet has nothing to show of itself
    public function testABookWithNoImageOfItsOwnShowsTheOneOfAnotherVersion(): void
    {
        $book = new Book()->setTitle('La Sorcière et les Policiers');
        $previous = new Book()->setTitle('La Sorcière et les Policiers — Édition originale');
        $previous->addExtract(new BookMedia()->setName('page-1.webp'));
        $previous->setNewerVersion($book);

        $this->assertSame('page-1.webp', (string) BookSectionsExtension::cover($book));

        // Its own wins as soon as it is there
        $book->addCover(new BookMedia()->setName('cover.webp'));

        $this->assertSame('cover.webp', (string) BookSectionsExtension::cover($book));
    }
}
