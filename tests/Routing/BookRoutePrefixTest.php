<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Routing;

use c975L\BookBundle\Routing\BookRoutePrefix;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;

// The first segment of every public page of this bundle: what matches here decides whether a book, a serie or a strip is served at all on this site (see the condition of the three controllers)
class BookRoutePrefixTest extends TestCase
{
    // The word an admin typed, whatever the slashes and the spaces it was typed with - the route parameter carries the segment alone
    public function testTheConfiguredWordIsReadWithoutItsSlashes(): void
    {
        $this->assertSame('livres', $this->prefix(['book-route-books' => '/livres/'])->get('book-route-books'));
        $this->assertSame('livres', $this->prefix(['book-route-books' => ' livres '])->get('book-route-books'));
    }

    // A site whose books are read elsewhere leaves the entry empty, and an entry that was never created reads the same
    public function testAnEmptyOrMissingEntryReadsAsNoPrefixAtAll(): void
    {
        $this->assertSame('', $this->prefix(['book-route-books' => ''])->get('book-route-books'));
        $this->assertSame('', $this->prefix([])->get('book-route-books'));
    }

    // What the routes' condition calls at each request
    public function testOnlyTheConfiguredSegmentMatches(): void
    {
        $prefix = $this->prefix(['book-route-books' => 'livres']);

        $this->assertTrue($prefix->matches('book-route-books', 'livres'));
        $this->assertFalse($prefix->matches('book-route-books', 'books'));
    }

    // The whole point of an empty entry: the pages are off the site rather than served under a default word - without this, "/{book_prefix}/{slug}" would swallow every two-segment url of the site
    public function testNothingMatchesWhileTheEntryIsEmpty(): void
    {
        $prefix = $this->prefix(['book-route-books' => '']);

        $this->assertFalse($prefix->matches('book-route-books', ''));
        $this->assertFalse($prefix->matches('book-route-books', 'livres'));
    }

    // The router hands over whatever the url held, which is not always a string
    public function testASegmentThatIsNoStringNeverMatches(): void
    {
        $prefix = $this->prefix(['book-route-books' => 'livres']);

        $this->assertFalse($prefix->matches('book-route-books', null));
        $this->assertFalse($prefix->matches('book-route-books', ['livres']));
    }

    // What the sitemap and the menu targets ask before offering a page (see BookSitemapProvider and LinkableRouteProvider)
    public function testAFamilyIsEnabledOnlyWhereItsEntryHoldsAWord(): void
    {
        $this->assertTrue($this->prefix(['book-route-series' => 'series'])->isEnabled('book-route-series'));
        $this->assertFalse($this->prefix(['book-route-series' => ' / '])->isEnabled('book-route-series'));
    }

    // Nine pairs, and no two of them naming the same thing: two families sharing an entry would serve one another's pages, and two route parameters bearing the same name would overwrite each other in the router's context (see BookRoutePrefixListener)
    public function testEachFamilyCarriesAnEntryAndAParameterOfItsOwn(): void
    {
        $this->assertCount(9, BookRoutePrefix::PREFIXES);
        $this->assertSame(BookRoutePrefix::PREFIXES, array_unique(BookRoutePrefix::PREFIXES));
        $this->assertCount(9, array_unique(array_keys(BookRoutePrefix::PREFIXES)));
    }

    /** @param array<string, string> $entries */
    private function prefix(array $entries): BookRoutePrefix
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService
            ->method('get')
            ->willReturnCallback(static fn (string $slug): ?string => $entries[$slug] ?? null)
        ;

        return new BookRoutePrefix($configService);
    }
}
