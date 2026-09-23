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
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\BookSocialContentSource;
use c975L\ConfigBundle\Service\SiteUrlResolver;
use PHPUnit\Framework\TestCase;

class BookSocialContentSourceTest extends TestCase
{
    private function createBook(int $id, string $published = '-1 year'): Book
    {
        $book = new Book()->setTitle('Livre ' . $id)->setSlug('livre-' . $id)->setSummary('<p>Une histoire</p>')->setPublished(new \DateTime($published));
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

        $urlResolver = $this->createStub(BookPublicUrlResolver::class);
        $urlResolver->method('resolve')->willReturnCallback(static fn (string $route, array $parameters): ?string => null === $siteUrl ? null : $siteUrl . '/livres/' . $parameters['slug']);

        $siteUrlResolver = $this->createStub(SiteUrlResolver::class);
        $siteUrlResolver->method('siteUrl')->willReturn($siteUrl);

        return new BookSocialContentSource($repository, $urlResolver, $siteUrlResolver, '/var/www/site');
    }

    // A back catalog goes out in the order it was written
    public function testTheOldestBookNotPostedYetIsHandedOver(): void
    {
        $content = $this->createSource([$this->createBook(3), $this->createBook(2), $this->createBook(1)])->getNextContent(['1']);

        $this->assertSame('2', $content?->sourceId);
        $this->assertSame('https://example.org/livres/livre-2', $content->url);
        $this->assertSame(['description' => 'Une histoire'], $content->variables);
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
}
