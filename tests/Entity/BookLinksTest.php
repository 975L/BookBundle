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
use c975L\BookBundle\Service\BookCustomizationRegistry;
use PHPUnit\Framework\TestCase;

class BookLinksTest extends TestCase
{
    /** @var array<string, BookLink> */
    private array $links = [];

    private function book(): Book
    {
        $book = new Book();
        foreach (['epub_kobo', 'epub_apple', 'podcast_spotify', 'video_youtube'] as $kind) {
            $this->links[$kind] = new BookLink()->setKind($kind)->setUrl('https://example.com/' . $kind);
            $book->addLink($this->links[$kind]);
        }

        return $book;
    }

    // Aucun site ne nomme de plateforme ici : le registre sert donc le vocabulaire du bundle
    private function registry(): BookCustomizationRegistry
    {
        return new BookCustomizationRegistry([]);
    }

    public function testLinksOfKeepsOnlyTheGroupAsked(): void
    {
        $book = $this->book();

        $this->assertSame(
            [$this->links['epub_kobo'], $this->links['epub_apple']],
            $this->registry()->getLinksOf($book, 'epub')
        );
    }

    public function testLinksOfTakesEachGroupApart(): void
    {
        $book = $this->book();

        $this->assertCount(1, $this->registry()->getLinksOf($book, 'podcast'));
        $this->assertCount(1, $this->registry()->getLinksOf($book, 'video'));
        $this->assertCount(0, $this->registry()->getLinksOf($book, 'audio'));
    }

    // Un groupe qui n'en est pas ne fait plus lever d'exception : le vocabulaire est celui du site, et un mot qu'il ne déclare pas ne désigne simplement aucun lien
    public function testAGroupThatIsNotOneHoldsNoLink(): void
    {
        $this->assertSame([], $this->registry()->getLinksOf($this->book(), 'epub_kobo'));
    }

    // Une plateforme que le site ne déclare pas relève d'aucune carte, plutôt que de faire tomber la page
    public function testAnUndeclaredPlatformBelongsToNoGroup(): void
    {
        $registry = new BookCustomizationRegistry([]);

        $this->assertNull($registry->getLinkGroup('boutique_inconnue'));
        $this->assertSame('boutique_inconnue', $registry->getLinkLabel('boutique_inconnue'));
    }

    public function testLinkFindsOnePlatformByName(): void
    {
        $book = $this->book();

        $this->assertSame($this->links['epub_kobo'], $book->getLink('epub_kobo'));
        $this->assertNull($book->getLink('epub_fnac'));
    }
}
