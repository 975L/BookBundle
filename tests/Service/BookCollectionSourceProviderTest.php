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
use c975L\BookBundle\Service\BookCollectionSourceProvider;
use c975L\BookBundle\Service\BookServiceInterface;
use PHPUnit\Framework\TestCase;

// The catalog offered to UiBundle's "collection" block: the whole of it, plus one source per language the catalog actually holds
class BookCollectionSourceProviderTest extends TestCase
{
    // A catalog in one language offers the whole of it and that language, and nothing else
    public function testOneSourcePerLanguageStandsBesideTheWholeCatalog(): void
    {
        $sources = $this->provider(['fr', 'en'])->getSources();

        $this->assertSame(
            ['book.collection.books', 'book.collection.books_fr', 'book.collection.books_en'],
            array_keys($sources),
        );
    }

    // Each language is named by its own endonym, never by the word the back office happens to be read in
    public function testALanguageIsNamedByItsEndonym(): void
    {
        $sources = $this->provider(['fr', 'es'])->getSources();

        $this->assertSame('Livres', $sources['book.collection.books']['label']);
        $this->assertSame('Livres (Français)', $sources['book.collection.books_fr']['label']);
        $this->assertSame('Livres (Español)', $sources['book.collection.books_es']['label']);
    }

    // Every source draws its rows with Book:Card, which the built-in card does not do
    public function testEverySourceNamesTheBundlesOwnItemTemplate(): void
    {
        foreach ($this->provider(['fr'])->getSources() as $source) {
            $this->assertSame('@c975LBook/collection/BookItem.html.twig', $source['itemTemplate']);
        }
    }

    // The book itself travels in "data": the template hands it to Book:Card, which reads its cover and its summary off it
    public function testAnItemCarriesTheBookItself(): void
    {
        $book = new Book()->setTitle('Le tracteur')->setSummary('Une histoire')->setSlug('le-tracteur');
        $sources = $this->provider(['fr'], [$book])->getSources();
        $items = $sources['book.collection.books']['items'](null);

        $this->assertCount(1, $items);
        $this->assertSame('Le tracteur', $items[0]->title);
        $this->assertSame('Une histoire', $items[0]->description);
        $this->assertSame('le-tracteur', $items[0]->slug);
        $this->assertSame($book, $items[0]->data['book']);
    }

    // A database not migrated yet must not take down the back-office form listing the sources
    public function testACatalogThatCannotBeReadOffersTheWholeCatalogAlone(): void
    {
        $bookService = $this->createStub(BookServiceInterface::class);
        $bookService->method('findLanguages')->willThrowException(new \RuntimeException('no such table'));

        $this->assertSame(['book.collection.books'], array_keys(new BookCollectionSourceProvider($bookService)->getSources()));
    }

    /**
     * @param list<string> $languages
     * @param list<Book>   $books
     */
    private function provider(array $languages, array $books = []): BookCollectionSourceProvider
    {
        $bookService = $this->createStub(BookServiceInterface::class);
        $bookService->method('findLanguages')->willReturn($languages);
        $bookService->method('findAllPublished')->willReturn($books);
        $bookService->method('countPublished')->willReturn(\count($books));

        return new BookCollectionSourceProvider($bookService);
    }
}
