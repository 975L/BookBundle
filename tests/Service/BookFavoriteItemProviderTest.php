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
use c975L\BookBundle\Service\BookFavoriteItemProvider;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Packages;

// The only place of this bundle that knows what "book" stands for, UiBundle storing a name and an id and nothing else
class BookFavoriteItemProviderTest extends TestCase
{
    public function testItAnswersForBooksAndForNothingElse(): void
    {
        $provider = $this->provider();

        $this->assertTrue($provider->supports('book'));
        $this->assertFalse($provider->supports('strip'));
        $this->assertFalse($provider->supports('shop_product'));
    }

    // Keyed by owner id: the caller asked in its own order and reads the answer by id, no query returning a set in any particular one
    public function testItemsAreKeyedByTheirOwnId(): void
    {
        $items = $this->provider($this->book(12, 'La Princesse et les Monstres', 'la-princesse'))->getItems('book', [12]);

        $this->assertArrayHasKey(12, $items);
        $this->assertSame('La Princesse et les Monstres', $items[12]->title);
        $this->assertSame('/histoires/la-princesse', $items[12]->url);
        $this->assertSame('la-princesse', $items[12]->slug);
    }

    // The repository leaves out what a visitor may not reach, so an id nobody may see is simply absent rather than named on a page
    public function testAnIdTheRepositoryDoesNotReturnIsAbsent(): void
    {
        $this->assertSame([], $this->provider()->getItems('book', [12]));
    }

    // A book carrying no cover shows a card with no image rather than a placeholder nobody chose
    public function testABookWithNoCoverCarriesNoImage(): void
    {
        $items = $this->provider($this->book(12, 'Sans couverture', 'sans-couverture'))->getItems('book', [12]);

        $this->assertNull($items[12]->imageUrl);
    }

    private function book(int $id, string $title, string $slug): Book
    {
        $book = new Book()->setTitle($title)->setSlug($slug);
        new \ReflectionProperty(Book::class, 'id')->setValue($book, $id);

        return $book;
    }

    private function provider(Book ...$books): BookFavoriteItemProvider
    {
        $repository = $this->createStub(BookRepository::class);
        $repository->method('findPublishedByIds')->willReturn($books);

        $urlResolver = $this->createStub(BookPublicUrlResolver::class);
        $urlResolver->method('resolvePath')->willReturnCallback(
            static fn (string $route, array $parameters): string => '/histoires/' . $parameters['slug']
        );

        return new BookFavoriteItemProvider($repository, $urlResolver, $this->createStub(Packages::class));
    }
}
