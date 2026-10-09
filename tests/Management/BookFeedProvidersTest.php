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
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Entity\StripMedia;
use c975L\BookBundle\Management\BookFeedProvider;
use c975L\BookBundle\Management\StripFeedProvider;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\BookServiceInterface;
use c975L\BookBundle\Service\StripServiceInterface;
use c975L\BookBundle\Tests\BookPublicUrlGeneratorTestTrait;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\LocalizedUrlGenerator;
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\ConfigBundle\Service\SiteUrlResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

// What /feed/strip.xml and /feed/book.xml list - see ConfigBundle's FeedRenderer
class BookFeedProvidersTest extends TestCase
{
    use BookPublicUrlGeneratorTestTrait;

    private function configService(?string $siteUrl): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        return $configService;
    }

    // A real BookPublicUrlResolver over a real UrlGenerator, so the urls asserted are the ones the routes produce
    private function resolver(?string $siteUrl, array $prefixes): BookPublicUrlResolver
    {
        $urlGenerator = $this->createUrlGenerator();

        return new BookPublicUrlResolver($this->configService($siteUrl), $this->createRoutePrefix($prefixes), new LocalizedUrlGenerator($urlGenerator, new SiteLocales(['fr'], 'fr'), new RequestStack()), $urlGenerator, new SiteLocales(['fr'], 'fr'));
    }

    private function stripProvider(array $strips, ?string $siteUrl = 'https://example.com', array $prefixes = []): StripFeedProvider
    {
        $stripService = $this->createStub(StripServiceInterface::class);
        $stripService->method('findAllPublished')->willReturn($strips);

        return new StripFeedProvider($stripService, $this->resolver($siteUrl, $prefixes), new SiteUrlResolver($this->configService($siteUrl)), $this->createStub(TranslatorInterface::class));
    }

    private function bookProvider(array $books, ?string $siteUrl = 'https://example.com', ?BookServiceInterface $bookService = null, RequestStack $requestStack = new RequestStack()): BookFeedProvider
    {
        if (null === $bookService) {
            $bookService = $this->createStub(BookServiceInterface::class);
            $bookService->method('findAllPublished')->willReturn($books);
        }

        return new BookFeedProvider($bookService, $this->resolver($siteUrl, []), new SiteUrlResolver($this->configService($siteUrl)), $this->createStub(TranslatorInterface::class), $requestStack);
    }

    private function strip(): Strip
    {
        return new Strip()
            ->setTitle('Planche 1')
            ->setSlug('planche-1')
            ->setPublished(new \DateTime('2026-10-01'))
            ->setModification(new \DateTime('2026-10-02'))
            ->addPageMedia(new StripMedia()->setName('medias/book/strip/planche-1.webp'));
    }

    public function testAStripLinksToItsPageWithTheImageAShareShows(): void
    {
        $entries = $this->stripProvider([$this->strip()])->getEntries(20);

        $this->assertCount(1, $entries);
        $this->assertStringStartsWith('https://example.com/', $entries[0]['url']);
        $this->assertStringEndsWith('/planche-1', $entries[0]['url']);
        $this->assertSame('2026-10-01', $entries[0]['updated']->format('Y-m-d'));
        $this->assertSame('https://example.com/medias/book/strip/planche-1.webp', $entries[0]['image']);
    }

    // A site that does not serve its planches has no feed of them either (see BookRoutePrefix)
    public function testStripsOffTheSiteGiveNoEntry(): void
    {
        $this->assertSame([], $this->stripProvider([$this->strip()], prefixes: ['book-route-strip' => ''])->getEntries(20));
        $this->assertSame([], $this->stripProvider([$this->strip()], siteUrl: null)->getEntries(20));
    }

    public function testABookLinksToItsPage(): void
    {
        $book = new Book()->setTitle('Tome 1')->setSlug('tome-1')->setPublished(new \DateTime('2026-09-15'))->setModification(new \DateTime('2026-09-20'));
        $entries = $this->bookProvider([$book])->getEntries(20);

        $this->assertCount(1, $entries);
        $this->assertStringEndsWith('/tome-1', $entries[0]['url']);
        $this->assertSame('Tome 1', $entries[0]['title']);
        $this->assertNull($entries[0]['image']);
    }

    // The books of the language the feed is read in: a catalog writing its translations as rows of their own would list each book twice otherwise
    public function testTheBooksAreThoseOfTheLanguageRead(): void
    {
        $request = new Request();
        $request->setLocale('en');
        $requestStack = new RequestStack([$request]);
        $bookService = $this->createMock(BookServiceInterface::class);
        $bookService->expects($this->once())->method('findAllPublished')->with(20, 'en')->willReturn([]);

        $this->bookProvider([], bookService: $bookService, requestStack: $requestStack)->getEntries(20);
    }
}
