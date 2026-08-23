<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Twig;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookLink;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use c975L\BookBundle\Twig\BookLinkExtension;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class BookLinkExtensionTest extends TestCase
{
    /** @var array<string, BookLink> */
    private array $links = [];

    // The platforms in the order the book carries them, an audiobook slipping between two podcasts
    private function book(): Book
    {
        $book = new Book();
        foreach (['podcast_spotify', 'podcast_deezer', 'audio_gplay', 'podcast_apple', 'epub_kobo'] as $kind) {
            $this->links[$kind] = new BookLink()->setKind($kind)->setUrl('https://example.com/' . $kind);
            $book->addLink($this->links[$kind]);
        }

        return $book;
    }

    // The affiliate as the site configured it, empty string for a site declaring none
    private function extension(string $affiliate = ''): BookLinkExtension
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $key): mixed => 'book-gplay-affiliate' === $key ? $affiliate : null);

        return new BookLinkExtension(new BookCustomizationRegistry([], self::registryTranslator()), $configService);
    }

    public function testLinksOfKeepsOnlyTheGroupsAsked(): void
    {
        $book = $this->book();

        $this->assertSame([$this->links['epub_kobo']], $this->extension()->linksOf($book, 'epub'));
    }

    // A card holding two groups prints them in the book's order and not group after group, failing which the audiobook would always open the podcasts list
    public function testACardHoldingTwoGroupsKeepsTheBookOrder(): void
    {
        $book = $this->book();

        $this->assertSame(
            [
                $this->links['podcast_spotify'],
                $this->links['podcast_deezer'],
                $this->links['audio_gplay'],
                $this->links['podcast_apple'],
            ],
            $this->extension()->linksOf($book, 'audio', 'podcast')
        );
    }

    // The configured affiliate is added to the stored address, which stays whole: nothing is rebuilt from the book
    public function testTheConfiguredAffiliateIsAddedToAGooglePlayAddress(): void
    {
        $link = new BookLink()->setKind('epub_gplay')->setUrl('https://play.google.com/store/books/details?id=fxGQLX8D-bIC');

        $this->assertSame(
            'https://play.google.com/store/books/details?id=fxGQLX8D-bIC&PAffiliateID=affiliate123',
            $this->extension('affiliate123')->url($link)
        );
    }

    // An address with no query opens its own, and the anchor stays last
    public function testTheAffiliateOpensTheQueryAndKeepsTheAnchorLast(): void
    {
        $extension = $this->extension('affiliate 123');

        $this->assertSame('https://play.google.com/store/books?PAffiliateID=affiliate%20123', $extension->url('https://play.google.com/store/books'));
        $this->assertSame('https://play.google.com/store/books?PAffiliateID=affiliate%20123#extrait', $extension->url('https://play.google.com/store/books#extrait'));
    }

    // What the site wrote in the address wins: an id already there is never overwritten
    public function testAnAddressAlreadyCarryingAnAffiliateIsLeftAlone(): void
    {
        $url = 'https://play.google.com/store/books/details?PAffiliateID=other&id=fxGQLX8D-bIC';

        $this->assertSame($url, $this->extension('affiliate123')->url($url));
    }

    // The other platforms know nothing of that parameter, and a site with no affiliate configured adds nothing anywhere
    public function testNothingIsAddedToAnotherPlatformNorWithoutAConfiguredAffiliate(): void
    {
        $this->assertSame('https://kobo.example/livre?aff=975l', $this->extension('affiliate123')->url('https://kobo.example/livre?aff=975l'));
        $this->assertSame('https://play.google.com/store/books/details?id=fxGQLX8D-bIC', $this->extension()->url('https://play.google.com/store/books/details?id=fxGQLX8D-bIC'));
        $this->assertSame('', $this->extension('affiliate123')->url(null));
    }

    // The translator the registry asks for: it returns the key as is, which the real one does for a brand - a label that is no translation key is not translated
    private static function registryTranslator(): TranslatorInterface
    {
        $translator = self::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }
}
