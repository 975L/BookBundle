<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Service;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Repository\SerieRepository;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\BookSocialContentSource;
use c975L\ConfigBundle\Service\SiteUrlResolver;
use c975L\UiBundle\Model\SocialContent;
use PHPUnit\Framework\TestCase;

class BookSocialContentSourceTest extends TestCase
{
    /** @var list<string> */
    private array $excludedIds = [];

    // The series ids the repository was asked to narrow the books to
    /** @var list<int> */
    private array $serieIds = [];

    // The series the index lists, answered by the series repository
    /** @var list<Serie> */
    private array $series = [];

    private function createSerie(int $id, string $title): Serie
    {
        $serie = new Serie()->setTitle($title);
        new \ReflectionProperty(Serie::class, 'id')->setValue($serie, $id);
        $this->series[] = $serie;

        return $serie;
    }

    private function createBook(int $id, string $published = '-1 year'): Book
    {
        $book = new Book()->setTitle('Livre ' . $id)->setSlug('livre-' . $id)->setSummary('<p>Une histoire</p>')->setLanguage('fr')->setPublished(new \DateTime($published));
        new \ReflectionProperty(Book::class, 'id')->setValue($book, $id);

        return $book;
    }

    /**
     * @param list<Book> $books most recently published first, as the repository answers
     */
    private function createSource(array $books, ?string $siteUrl = 'https://example.org'): BookSocialContentSource
    {
        $repository = $this->createStub(BookRepository::class);
        $repository->method('findAllPublished')->willReturn($books);
        $repository->method('find')->willReturn($books[0] ?? null);
        $repository->method('findPostableLatest')->willReturnCallback(function (array $excludedIds, int $limit, array $serieIds = []) use ($books): array {
            $this->excludedIds = $excludedIds;
            $this->serieIds = $serieIds;

            return $books;
        });

        $urlResolver = $this->createStub(BookPublicUrlResolver::class);
        $urlResolver->method('resolve')->willReturnCallback(static fn (string $route, array $parameters): ?string => null === $siteUrl ? null : $siteUrl . '/livres/' . $parameters['slug']);

        $siteUrlResolver = $this->createStub(SiteUrlResolver::class);
        $siteUrlResolver->method('siteUrl')->willReturn($siteUrl);

        $serieRepository = $this->createStub(SerieRepository::class);
        $serieRepository->method('findWithBooks')->willReturnCallback(fn (): array => $this->series);

        return new BookSocialContentSource($repository, $serieRepository, $urlResolver, $siteUrlResolver, '/var/www/site');
    }

    // A back catalog goes out in the order it was written
    public function testTheOldestBookNotPostedYetIsHandedOver(): void
    {
        $content = $this->createSource([$this->createBook(3), $this->createBook(2), $this->createBook(1)])->getNextContent(['1']);

        $this->assertSame('2', $content?->sourceId);
        $this->assertSame('https://example.org/livres/livre-2', $content->url);
        $this->assertSame(['description' => 'Une histoire'], $content->variables);
        $this->assertSame('fr', $content->locale);
    }

    // The book pages are not served on every site: no url, no post
    public function testNothingIsHandedOverWithoutAPublicUrl(): void
    {
        $this->assertNull($this->createSource([$this->createBook(1)], null)->getNextContent([]));
    }

    public function testABookIsRecalledASeasonLater(): void
    {
        $this->assertSame(90, $this->createSource([])->getRepeatAfterDays());
    }

    public function testABookTakenOffTheCatalogSinceIsNotReadAgain(): void
    {
        $book = $this->createBook(1);
        $this->assertSame('1', $this->createSource([$book])->getContent('1')?->sourceId);

        $book->setHidden(true);
        $this->assertNull($this->createSource([$book])->getContent('1'));
    }

    public function testABookNotPublishedYetIsNotReadAgain(): void
    {
        $this->assertNull($this->createSource([$this->createBook(1, '+1 month')])->getContent('1'));
    }

    // What a post's book is chosen among: the ones still free, the latest first, those without a public url left out
    public function testTheContentsToChooseAreTheFreeBooksLatestFirst(): void
    {
        $contents = $this->createSource([$this->createBook(3), $this->createBook(2)])->findContents(['1'], ['9'], 48);

        $this->assertSame(['3', '2'], array_map(static fn (SocialContent $content): string => $content->sourceId, $contents));
        $this->assertSame(['1'], $this->excludedIds);
        $this->assertSame([9], $this->serieIds);
        $this->assertSame([], $this->createSource([$this->createBook(3)], null)->findContents([], [], 48));
    }

    // A book is drawn again from its own series, a book of none from the whole catalog
    public function testABooksScopeIsItsSeries(): void
    {
        $book = $this->createBook(1)->setSerie($this->createSerie(4, 'Mamie ViteVite'));

        $this->assertSame('4', $this->createSource([$book])->getContentScope('1'));
        $this->assertNull($this->createSource([$this->createBook(1)])->getContentScope('1'));
    }

    // The series the index lists are the groups a series of posts picks among
    public function testTheSeriesAreTheScopes(): void
    {
        $this->createSerie(4, 'Mamie ViteVite');
        $this->createSerie(7, 'Papa Câlin');

        $this->assertSame(['4' => 'Mamie ViteVite', '7' => 'Papa Câlin'], $this->createSource([])->getScopes());
    }

    // Narrowed to some series, the oldest book of those goes out, a book of another series or of none skipped
    public function testTheNextBookIsTakenFromTheSeriesGiven(): void
    {
        $mamie = $this->createSerie(4, 'Mamie ViteVite');
        $papa = $this->createSerie(7, 'Papa Câlin');
        $books = [$this->createBook(4)->setSerie($mamie), $this->createBook(3)->setSerie($papa), $this->createBook(2), $this->createBook(1)->setSerie($papa)];

        $this->assertSame('4', $this->createSource($books)->getNextScopedContent([], ['4'])?->sourceId);
        $this->assertSame('3', $this->createSource($books)->getNextScopedContent(['1'], ['7'])?->sourceId);
        $this->assertNull($this->createSource($books)->getNextScopedContent(['4'], ['4']));
        $this->assertSame('1', $this->createSource($books)->getNextContent([])?->sourceId);
    }
}
