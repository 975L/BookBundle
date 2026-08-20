<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Entity;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookLink;
use c975L\BookBundle\Enum\BookLinkKind;
use PHPUnit\Framework\TestCase;

class BookLinkTest extends TestCase
{
    public function testUrlIsTheStoredAddressAndNothingIsRebuiltFromTheBook(): void
    {
        $link = new BookLink()
            ->setKind(BookLinkKind::EpubGplay)
            ->setUrl('https://play.google.com/store/books/details?PAffiliateID=1011l46DJf&id=fxGQLX8D-bIC');

        $this->assertSame('https://play.google.com/store/books/details?PAffiliateID=1011l46DJf&id=fxGQLX8D-bIC', $link->getUrl());
    }

    public function testLabelAndIconComeFromTheKind(): void
    {
        $link = new BookLink()->setKind(BookLinkKind::EpubKobo);

        $this->assertSame('Kobo', $link->getLabel());
        $this->assertSame('bundles/c975lbook/icons/kobo.svg', $link->getIcon());
        $this->assertSame('Kobo', (string) $link);
    }

    public function testAKindlessLinkSaysNothingRatherThanFailing(): void
    {
        $link = new BookLink();

        $this->assertNull($link->getLabel());
        $this->assertNull($link->getIcon());
        $this->assertSame('', (string) $link);
    }

    public function testPositionFallsBackToTheFirstPlace(): void
    {
        $this->assertSame(0, new BookLink()->setPosition(null)->getPosition());
        $this->assertSame(3, new BookLink()->setPosition(3)->getPosition());
    }

    public function testAddingALinkToABookSetsBothSidesOfTheAssociation(): void
    {
        $book = new Book();
        $link = new BookLink()->setKind(BookLinkKind::EpubFnac);

        $book->addLink($link);
        $this->assertSame($book, $link->getBook());
        $this->assertCount(1, $book->getLinks());

        // Added twice, a link is held once
        $book->addLink($link);
        $this->assertCount(1, $book->getLinks());

        $book->removeLink($link);
        $this->assertCount(0, $book->getLinks());
        $this->assertNull($link->getBook());
    }
}
