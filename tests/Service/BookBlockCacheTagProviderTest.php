<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Service;

use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Repository\StripRepository;
use c975L\BookBundle\Service\BookBlockCacheInvalidator;
use c975L\BookBundle\Service\BookBlockCacheTagProvider;
use c975L\UiBundle\Entity\Block;
use PHPUnit\Framework\TestCase;

// What lets this bundle's kinds be cached at all: the tag their entry carries, and the case where caching is declined
class BookBlockCacheTagProviderTest extends TestCase
{
    // The six kinds querying the catalog live; the reader is left out, its content being its own attached medias
    public function testEveryKindQueryingTheCatalogHasItsResolver(): void
    {
        $resolvers = $this->resolvers();

        $this->assertSame(
            ['book_series', 'book_categories', 'book_contributors', 'book_books', 'book_to_be_published', 'book_serie_strips'],
            array_keys($resolvers)
        );
        $this->assertArrayNotHasKey('book_reader', $resolvers);
    }

    // A serie is listed whatever the hour, and so is a person credited on one: nothing but a save changes what they show
    public function testAListingReadingNoDateIsCachedWhateverIsScheduled(): void
    {
        $resolvers = $this->resolvers(true, true);

        foreach (['book_series', 'book_contributors'] as $kind) {
            $this->assertSame([BookBlockCacheInvalidator::CACHE_TAG_CATALOG], $resolvers[$kind](new Block()), $kind);
        }
    }

    // Nothing dated ahead, nothing to go stale: the two listings are cached like the rest
    public function testTheBookListingsAreCachedWhenNothingIsScheduled(): void
    {
        $resolvers = $this->resolvers();

        foreach (['book_books', 'book_to_be_published'] as $kind) {
            $this->assertSame([BookBlockCacheInvalidator::CACHE_TAG_CATALOG], $resolvers[$kind](new Block()), $kind);
        }
    }

    // The day a scheduled book comes out, no row is saved and no tag is dropped - an entry cached the day before would hold it back for good
    public function testAScheduledBookDeclinesTheEntryOfTheBookListings(): void
    {
        $resolvers = $this->resolvers(true);

        foreach (['book_books', 'book_to_be_published'] as $kind) {
            $this->assertNull($resolvers[$kind](new Block()), $kind);
        }

        // The planches are asked their own question: a serie publishing one a week would otherwise never let a book listing be cached
        $this->assertSame([BookBlockCacheInvalidator::CACHE_TAG_CATALOG], $resolvers['book_serie_strips'](new Block()));
    }

    public function testAScheduledStripDeclinesTheEntryOfTheStripListing(): void
    {
        $resolvers = $this->resolvers(false, true);

        $this->assertNull($resolvers['book_serie_strips'](new Block()));
        $this->assertSame([BookBlockCacheInvalidator::CACHE_TAG_CATALOG], $resolvers['book_books'](new Block()));
    }

    // A listing drawing its rows at random is rendered live whatever the calendar: a cached entry would freeze one single draw until the catalog itself changed
    public function testAListingDrawingAtRandomDeclinesItsEntry(): void
    {
        $resolvers = $this->resolvers();
        $block = new Block()->setData(['random' => true]);

        foreach (array_keys($resolvers) as $kind) {
            $this->assertNull($resolvers[$kind]($block), $kind);
        }
    }

    // Three book blocks on the same page ask the same question once, and ask it again once the request is over
    public function testTheAnswerIsReadOncePerRequest(): void
    {
        $bookRepository = $this->createMock(BookRepository::class);
        $bookRepository->expects($this->exactly(2))->method('hasScheduled')->willReturn(false);

        $provider = new BookBlockCacheTagProvider($bookRepository, $this->repository(StripRepository::class, false));
        $resolvers = $provider->getCacheTagResolvers();

        $resolvers['book_books'](new Block());
        $resolvers['book_books'](new Block());
        $resolvers['book_to_be_published'](new Block());

        $provider->reset();
        $resolvers['book_books'](new Block());
    }

    private function resolvers(bool $scheduledBook = false, bool $scheduledStrip = false): array
    {
        return new BookBlockCacheTagProvider(
            $this->repository(BookRepository::class, $scheduledBook),
            $this->repository(StripRepository::class, $scheduledStrip),
        )->getCacheTagResolvers();
    }

    private function repository(string $class, bool $scheduled): BookRepository | StripRepository
    {
        $repository = $this->createStub($class);
        $repository->method('hasScheduled')->willReturn($scheduled);

        return $repository;
    }
}
