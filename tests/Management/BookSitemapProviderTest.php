<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Management;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookCategory;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Management\BookSitemapProvider;
use c975L\BookBundle\Service\BookCategoryServiceInterface;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\BookServiceInterface;
use c975L\BookBundle\Service\ContributorServiceInterface;
use c975L\BookBundle\Service\SerieServiceInterface;
use c975L\BookBundle\Service\StripServiceInterface;
use c975L\BookBundle\Tests\BookPublicUrlGeneratorTestTrait;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

// What public/sitemap-book.xml declares, and what public/llms.txt is built from - see ConfigBundle's SitemapWriter and SeoFilesWriter
class BookSitemapProviderTest extends TestCase
{
    use BookPublicUrlGeneratorTestTrait;

    // A real BookPublicUrlResolver over a real UrlGenerator, so the urls asserted below are the ones the routes actually produce
    private function createProvider(
        array $books = [],
        array $series = [],
        array $strips = [],
        array $contributors = [],
        array $categories = [],
        string $siteUrl = 'https://example.com',
        array $prefixes = [],
    ): BookSitemapProvider {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        $bookService = $this->createStub(BookServiceInterface::class);
        // The sitemap lists every page that answers, replaced versions included (see BookRepository::findAllOnline())
        $bookService->method('findAllOnline')->willReturn($books);
        $serieService = $this->createStub(SerieServiceInterface::class);
        $serieService->method('findAll')->willReturn($series);
        $stripService = $this->createStub(StripServiceInterface::class);
        $stripService->method('findAllPublished')->willReturn($strips);
        // Only the people a shown book or serie still credits, which is what the index lists too (see ContributorRepository::findCredited())
        $contributorService = $this->createStub(ContributorServiceInterface::class);
        $contributorService->method('findCredited')->willReturn($contributors);
        // Only the categories holding a book the site shows, which is what their index lists too (see BookCategoryRepository::findWithBooks())
        $categoryService = $this->createStub(BookCategoryServiceInterface::class);
        $categoryService->method('findWithBooks')->willReturn($categories);

        // The key itself as its own translation, so what an url carries can be told from what a catalog says
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new BookSitemapProvider(
            new BookPublicUrlResolver($configService, $this->createRoutePrefix($prefixes), $this->createUrlGenerator()),
            $categoryService,
            $bookService,
            $contributorService,
            $serieService,
            $stripService,
            $translator
        );
    }

    private function book(): Book
    {
        return new Book()
            ->setTitle('Tome 1')
            ->setSlug('tome-1')
            ->setSummary('Une histoire de pirates')
            ->setModification(new \DateTime('2026-01-15'));
    }

    private function serie(): Serie
    {
        return new Serie()
            ->setTitle('La Compagnie des Ombres')
            ->setSlug('la-compagnie-des-ombres')
            ->setSummary('Le résumé de la série')
            ->setModification(new \DateTime('2026-02-20'));
    }

    private function strip(): Strip
    {
        return new Strip()
            ->setTitle('Planche 1')
            ->setSlug('planche-1')
            ->setModification(new \DateTime('2026-03-25'));
    }

    private function category(): BookCategory
    {
        return new BookCategory()
            ->setTitle('Romans')
            ->setSlug('romans')
            ->setSummary('Le résumé de la catégorie')
            ->setModification(new \DateTime('2026-05-10'));
    }

    private function contributor(): Contributor
    {
        return new Contributor()
            ->setName('Tim Loval')
            ->setSlug('tim-loval')
            ->setSummary('Auteur des Triados')
            ->setModification(new \DateTime('2026-04-30'));
    }

    // The name is what the written file is called, public/sitemap-book.xml, and what the index declares
    public function testSitemapNameIsBook(): void
    {
        $this->assertSame('book', $this->createProvider()->getSitemapName());
    }

    // The four listing pages open the sitemap, even with nothing published yet - each is a real page of the site
    public function testGetUrlsAlwaysDeclaresTheFourIndexes(): void
    {
        $urls = $this->createProvider()->getUrls();

        $this->assertSame(
            ['https://example.com/livres', 'https://example.com/series', 'https://example.com/strips', 'https://example.com/auteurs'],
            array_column($urls, 'loc')
        );
        $this->assertSame(['label.books', 'label.series', 'label.strips_series', 'label.contributors'], array_column($urls, 'title'));
    }

    // A published book is declared under its own route, with its modification date as lastmod
    public function testGetUrlsDeclaresAPublishedBook(): void
    {
        $urls = $this->createProvider([$this->book()])->getUrls();

        $this->assertSame([
            'loc' => 'https://example.com/livre/tome-1',
            'lastmod' => '2026-01-15',
            'changefreq' => 'monthly',
            'priority' => 8,
            'title' => 'Tome 1',
            'description' => 'Une histoire de pirates',
        ], $urls[1]);
    }

    public function testGetUrlsDeclaresASerie(): void
    {
        $urls = $this->createProvider([], [$this->serie()])->getUrls();

        $this->assertSame([
            'loc' => 'https://example.com/series/la-compagnie-des-ombres',
            'lastmod' => '2026-02-20',
            'changefreq' => 'monthly',
            'priority' => 8,
            'title' => 'La Compagnie des Ombres',
            'description' => 'Le résumé de la série',
        ], $urls[2]);
    }

    // A strip carries no title, and so contributes nothing to llms.txt - an index of plates being what the sitemap already is
    public function testGetUrlsDeclaresAStripWithoutTitleNorDescription(): void
    {
        $urls = $this->createProvider([], [], [$this->strip()])->getUrls();

        $this->assertSame([
            'loc' => 'https://example.com/strip/planche-1',
            'lastmod' => '2026-03-25',
            'changefreq' => 'monthly',
            'priority' => 6,
        ], $urls[3]);
    }

    // A person the catalog credits carries a title and a description, so their page is one of the lines llms.txt is built from
    public function testGetUrlsDeclaresACreditedPerson(): void
    {
        $urls = $this->createProvider([], [], [], [$this->contributor()])->getUrls();

        $this->assertSame([
            'loc' => 'https://example.com/auteur/tim-loval',
            'lastmod' => '2026-04-30',
            'changefreq' => 'monthly',
            'priority' => 7,
            'title' => 'Tim Loval',
            'description' => 'Auteur des Triados',
        ], $urls[4]);
    }

    // Without "site-url", BookPublicUrlResolver can't build absolute urls, and a sitemap accepts nothing else
    public function testGetUrlsReturnsEmptyArrayWhenSiteUrlIsNotConfigured(): void
    {
        $this->assertSame([], $this->createProvider([$this->book()], [$this->serie()], [$this->strip()], [$this->contributor()], [], '')->getUrls());
    }

    // A fresh install serves no category page at all, its ConfigBundle entry being empty: nothing of the family is declared until the site fills it in
    public function testGetUrlsDeclaresNoCategoryUntilTheSiteServesThem(): void
    {
        $urls = $this->createProvider(categories: [$this->category()])->getUrls();

        $this->assertSame(['https://example.com/livres', 'https://example.com/series', 'https://example.com/strips', 'https://example.com/auteurs'], array_column($urls, 'loc'));
    }

    // Once the site says under which word it serves them, the index and each category are declared like any other family
    public function testGetUrlsDeclaresACategoryWhereTheSiteServesThem(): void
    {
        $urls = $this->createProvider(
            categories: [$this->category()],
            prefixes: ['book-route-categories' => 'categories']
        )->getUrls();

        $category = array_values(array_filter($urls, static fn (array $url): bool => 'https://example.com/categories/romans' === $url['loc']))[0];
        $this->assertSame('Romans', $category['title']);
        $this->assertSame('Le résumé de la catégorie', $category['description']);
        $this->assertSame('2026-05-10', $category['lastmod']);
        $this->assertContains('https://example.com/categories', array_column($urls, 'loc'));
    }

    // A family read elsewhere has no page on this site, index included - declaring one would advertise an url the router answers nothing for
    public function testGetUrlsLeavesOutAFamilyNotServedHere(): void
    {
        $provider = $this->createProvider(
            [$this->book()],
            [],
            [$this->strip()],
            [$this->contributor()],
            [],
            'https://example.com',
            ['book-route-strips' => '', 'book-route-strip' => '', 'book-route-contributors' => '', 'book-route-contributor' => '']
        );

        $this->assertSame(
            ['https://example.com/livres', 'https://example.com/livre/tome-1', 'https://example.com/series'],
            array_column($provider->getUrls(), 'loc')
        );
    }
}
