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
use Symfony\Contracts\Translation\TranslatorInterface;

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

    // No site names a platform here: the registry therefore serves the bundle's own vocabulary
    private function registry(): BookCustomizationRegistry
    {
        return new BookCustomizationRegistry([], self::registryTranslator());
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

    // A group that is none raises no exception any more: the vocabulary is the site's, and a word it does not declare simply names no link
    public function testAGroupThatIsNotOneHoldsNoLink(): void
    {
        $this->assertSame([], $this->registry()->getLinksOf($this->book(), 'epub_kobo'));
    }

    // A platform the site does not declare belongs to no card, rather than bringing the page down
    public function testAnUndeclaredPlatformBelongsToNoGroup(): void
    {
        $registry = new BookCustomizationRegistry([], self::registryTranslator());

        $this->assertNull($registry->getLinkGroup('boutique_inconnue'));
        $this->assertSame('boutique_inconnue', $registry->getLinkLabel('boutique_inconnue'));
    }

    public function testLinkFindsOnePlatformByName(): void
    {
        $book = $this->book();

        $this->assertSame($this->links['epub_kobo'], $book->getLink('epub_kobo'));
        $this->assertNull($book->getLink('epub_fnac'));
    }

    // The translator the registry asks for: it returns the key as is, which the real one does for a brand - a label that is no translation key is not translated
    private static function registryTranslator(): TranslatorInterface
    {
        $translator = self::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }
}
