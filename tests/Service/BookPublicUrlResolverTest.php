<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Service;

use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Enum\SerieKind;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Tests\BookPublicUrlGeneratorTestTrait;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;

// The single place this bundle's public urls are spelled - what the sitemap declares, hence what a crawler is sent to
class BookPublicUrlResolverTest extends TestCase
{
    use BookPublicUrlGeneratorTestTrait;

    private function createResolver(string $siteUrl = 'https://example.com', array $prefixes = []): BookPublicUrlResolver
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        return new BookPublicUrlResolver($configService, $this->createRoutePrefix($prefixes), $this->createUrlGenerator());
    }

    // The path comes from the routes themselves, its first segment from the site's own configuration, the host from "site-url"
    public function testResolveBuildsAnAbsoluteUrlFromTheRoute(): void
    {
        $resolver = $this->createResolver();

        $this->assertSame('https://example.com/livres', $resolver->resolve('book_index'));
        $this->assertSame('https://example.com/livre/tome-1', $resolver->resolve('book_display', ['slug' => 'tome-1']));
        $this->assertSame('https://example.com/series/la-compagnie', $resolver->resolve('serie_display', ['slug' => 'la-compagnie']));
        $this->assertSame('https://example.com/strip/planche-1', $resolver->resolve('strip_display', ['slug' => 'planche-1']));
    }

    // A serie sits below the index listing it, sharing its very segment - which of the two being what its kind says (see SerieKind), a serie declaring none being filed by what it holds
    public function testASerieIsReadBelowTheIndexListingIt(): void
    {
        $resolver = $this->createResolver();
        $books = new Serie()->setSlug('la-compagnie')->setKind(SerieKind::Book->value);
        $strips = new Serie()->setSlug('repliques')->setKind(SerieKind::Strip->value);

        $this->assertSame('serie_display', BookPublicUrlResolver::serieRoute($books));
        $this->assertSame('strip_serie_display', BookPublicUrlResolver::serieRoute($strips));
        $this->assertSame('https://example.com/series/la-compagnie', $resolver->resolve(BookPublicUrlResolver::serieRoute($books), ['slug' => 'la-compagnie']));
        $this->assertSame('https://example.com/strips/repliques', $resolver->resolve(BookPublicUrlResolver::serieRoute($strips), ['slug' => 'repliques']));
    }

    // A site serving its catalog in its own words is followed there, the prefix being read at each call rather than baked in
    public function testResolveFollowsTheConfiguredPrefixes(): void
    {
        $resolver = $this->createResolver('https://example.com', ['book-route-books' => 'books', 'book-route-book' => 'book']);

        $this->assertSame('https://example.com/books', $resolver->resolve('book_index'));
        $this->assertSame('https://example.com/book/volume-1', $resolver->resolve('book_display', ['slug' => 'volume-1']));
    }

    // An empty prefix takes the family off the site (the routes match nothing), so there is no url to hand back for it
    public function testResolveReturnsNullForAFamilyNotServedHere(): void
    {
        $resolver = $this->createResolver('https://example.com', ['book-route-strips' => '', 'book-route-strip' => '']);

        $this->assertNull($resolver->resolve('strip_index'));
        $this->assertNull($resolver->resolve('strip_display', ['slug' => 'planche-1']));
        $this->assertSame('https://example.com/livres', $resolver->resolve('book_index'));
    }

    // A "site-url" saved with a trailing slash would otherwise double the one every generated path opens with
    public function testResolveDoesNotDoubleTheSlashOfAConfiguredSiteUrl(): void
    {
        $this->assertSame('https://example.com/livres', $this->createResolver('https://example.com/')->resolve('book_index'));
    }

    // Without "site-url", no absolute url can be built - and a sitemap accepts nothing else
    public function testResolveReturnsNullWhenSiteUrlIsNotConfigured(): void
    {
        $this->assertNull($this->createResolver('')->resolve('book_index'));
        $this->assertNull($this->createResolver('   ')->resolve('book_display', ['slug' => 'tome-1']));
    }

    // The local part alone, for anything running on the site itself rather than against its public host
    public function testResolvePathReturnsThePathWithoutTheHost(): void
    {
        $this->assertSame('/livre/tome-1', $this->createResolver('')->resolvePath('book_display', ['slug' => 'tome-1']));
    }

    // A route that isn't one of the six public ones is a mistake to fix, not something to absorb into a silent null
    public function testResolvePathRejectsAnUnknownRoute(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->createResolver()->resolvePath('management');
    }
}
