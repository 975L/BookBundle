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
use c975L\BookBundle\Enum\BookLinkGroup;
use c975L\BookBundle\Enum\BookLinkKind;
use PHPUnit\Framework\TestCase;

class BookLinksTest extends TestCase
{
    /** @var array<string, BookLink> */
    private array $links = [];

    private function book(): Book
    {
        $book = new Book();
        foreach ([BookLinkKind::EpubKobo, BookLinkKind::EpubApple, BookLinkKind::PodcastSpotify, BookLinkKind::VideoYoutube] as $kind) {
            $this->links[$kind->value] = new BookLink()->setKind($kind)->setUrl('https://example.com/' . $kind->value);
            $book->addLink($this->links[$kind->value]);
        }

        return $book;
    }

    public function testLinksOfKeepsOnlyTheGroupAsked(): void
    {
        $stores = $this->book()->getLinksOf(BookLinkGroup::Epub);

        $this->assertSame(
            [$this->links['epub_kobo'], $this->links['epub_apple']],
            array_values($stores->toArray())
        );
    }

    public function testLinksOfTakesTheGroupAsAStringToo(): void
    {
        // A template names its group by hand, "podcast" rather than the case itself
        $this->assertCount(1, $this->book()->getLinksOf('podcast'));
        $this->assertCount(1, $this->book()->getLinksOf('video'));
        $this->assertCount(0, $this->book()->getLinksOf('audio'));
    }

    public function testLinksOfRefusesAGroupThatIsNotOne(): void
    {
        $this->expectException(\ValueError::class);

        $this->book()->getLinksOf('epub_kobo');
    }

    public function testLinkFindsOnePlatformByName(): void
    {
        $book = $this->book();

        $this->assertSame($this->links['epub_kobo'], $book->getLink('epub_kobo'));
        $this->assertSame($this->links['epub_kobo'], $book->getLink(BookLinkKind::EpubKobo));
        $this->assertNull($book->getLink(BookLinkKind::EpubFnac));
    }
}
