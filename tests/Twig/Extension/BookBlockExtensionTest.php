<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Twig\Extension;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookCategory;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Service\BookCategoryServiceInterface;
use c975L\BookBundle\Service\BookServiceInterface;
use c975L\BookBundle\Service\ContributorServiceInterface;
use c975L\BookBundle\Service\SerieServiceInterface;
use c975L\BookBundle\Service\StripServiceInterface;
use c975L\BookBundle\Twig\Extension\BookBlockExtension;
use PHPUnit\Framework\TestCase;

// What the six catalog block kinds put on a page, resolved live at render rather than stored in the block's data
class BookBlockExtensionTest extends TestCase
{
    /** @return list<Book> */
    private function books(int $count): array
    {
        return array_map(static fn (int $i) => new Book()->setTitle('Livre ' . $i)->setSlug('livre-' . $i), range(1, $count));
    }

    /** @return list<Serie> */
    private function series(string ...$slugs): array
    {
        return array_map(static fn (string $slug) => new Serie()->setTitle($slug)->setSlug($slug), $slugs);
    }

    // Doctrine assigns the identifier itself, which a row built here never goes through
    private function setId(object $entity, int $id): void
    {
        new \ReflectionProperty($entity::class, 'id')->setValue($entity, $id);
    }

    private function extension(
        ?BookServiceInterface $bookService = null,
        ?SerieServiceInterface $serieService = null,
        ?BookCategoryServiceInterface $categoryService = null,
        ?ContributorServiceInterface $contributorService = null,
    ): BookBlockExtension {
        return new BookBlockExtension(
            $categoryService ?? $this->createStub(BookCategoryServiceInterface::class),
            $bookService ?? $this->createStub(BookServiceInterface::class),
            $contributorService ?? $this->createStub(ContributorServiceInterface::class),
            $serieService ?? $this->createStub(SerieServiceInterface::class),
            $this->createStub(StripServiceInterface::class),
        );
    }

    // Without a draw, the maximum is the query's business: the catalog is cut in the database rather than read whole
    public function testWithoutADrawTheMaximumIsAskedOfTheQuery(): void
    {
        $bookService = $this->createMock(BookServiceInterface::class);
        $bookService->expects($this->once())->method('findAllPublished')->with(4)->willReturn($this->books(4));

        $this->assertCount(4, $this->extension(bookService: $bookService)->getBooks(4));
    }

    // A draw reads the whole catalog first: shuffling what the maximum already kept would only ever reorder the same four books
    public function testADrawReadsTheWholeCatalogBeforeCuttingItDown(): void
    {
        $bookService = $this->createMock(BookServiceInterface::class);
        $bookService->expects($this->once())->method('findAllPublished')->with(null)->willReturn($this->books(20));

        $this->assertCount(4, $this->extension(bookService: $bookService)->getBooks(4, random: true));
    }

    // The slug is what the block stores, its data being JSON and holding no entity
    public function testABlockNamingACategoryReadsThatShelfAlone(): void
    {
        $bookService = $this->createMock(BookServiceInterface::class);
        $bookService->expects($this->once())->method('findPublishedByCategory')->with('romans', 3)->willReturn($this->books(3));
        $bookService->expects($this->never())->method('findAllPublished');

        $this->assertCount(3, $this->extension(bookService: $bookService)->getBooks(3, categorySlug: 'romans'));
    }

    // An empty slug is a block naming no category, which is the whole catalog and not an empty shelf
    public function testAnEmptySlugIsTheWholeCatalog(): void
    {
        $bookService = $this->createMock(BookServiceInterface::class);
        $bookService->expects($this->once())->method('findAllPublished')->willReturn($this->books(2));
        $bookService->expects($this->never())->method('findPublishedByCategory');

        $this->assertCount(2, $this->extension(bookService: $bookService)->getBooks(categorySlug: ''));
    }

    // Only the categories holding a book the site shows, as their own index lists them
    public function testTheCategoriesAreTheOnesHoldingABook(): void
    {
        $categoryService = $this->createMock(BookCategoryServiceInterface::class);
        $categoryService->expects($this->once())->method('findWithBooks')->willReturn([new BookCategory(), new BookCategory(), new BookCategory()]);

        $this->assertCount(2, $this->extension(categoryService: $categoryService)->getCategories(2));
    }

    // The people a catalog credits are a handful, so the whole list is read and the maximum cuts it here
    public function testThePeopleAreReadWholeAndCutAfterwards(): void
    {
        $contributorService = $this->createMock(ContributorServiceInterface::class);
        $contributorService->expects($this->once())->method('findCredited')->willReturn([new Contributor(), new Contributor(), new Contributor()]);

        $this->assertCount(2, $this->extension(contributorService: $contributorService)->getContributors(2));
    }

    // No maximum means the whole list, a block left blank printing everything the catalog holds
    public function testNoMaximumKeepsEverything(): void
    {
        $serieService = $this->createMock(SerieServiceInterface::class);
        $serieService->expects($this->once())->method('findAll')->with(null)->willReturn([new Serie(), new Serie()]);

        $this->assertCount(2, $this->extension(serieService: $serieService)->getSeries());
    }

    // Named series are read in the order they were typed and not the catalog's: every serie of a site commonly sits at position 0, where that order is arbitrary
    public function testNamedSeriesAreReadInTheOrderTheyWereNamed(): void
    {
        $serieService = $this->createStub(SerieServiceInterface::class);
        $serieService->method('findAll')->willReturn($this->series('mamie-vitevite', 'papa-calin', 'les-triados'));

        $picked = $this->extension(serieService: $serieService)->getSeries(serieSlugs: 'papa-calin, les-triados');

        $this->assertSame(['papa-calin', 'les-triados'], array_map(static fn (Serie $serie): ?string => $serie->getSlug(), $picked));
    }

    // A slug naming nothing is simply absent, which is what makes a typo visible - falling back on the whole catalog would print a row nobody asked for
    public function testASlugNamingNoSerieIsLeftOutRatherThanWideningTheRow(): void
    {
        $serieService = $this->createStub(SerieServiceInterface::class);
        $serieService->method('findAll')->willReturn($this->series('papa-calin', 'les-triados'));

        $this->assertSame([], $this->extension(serieService: $serieService)->getSeries(serieSlugs: 'inconnue'));
        $this->assertCount(1, $this->extension(serieService: $serieService)->getSeries(serieSlugs: 'inconnue, papa-calin'));
    }

    // An empty field is no selection at all: the block then shows the whole catalog, which is what every one stored before the field existed does
    public function testAnEmptySelectionStillReadsTheWholeCatalog(): void
    {
        $serieService = $this->createMock(SerieServiceInterface::class);
        $serieService->expects($this->once())->method('findAll')->with(null)->willReturn($this->series('papa-calin', 'les-triados'));

        $this->assertCount(2, $this->extension(serieService: $serieService)->getSeries(serieSlugs: '  '));
    }

    // The count each card of a row prints, asked once for the whole row rather than per serie
    public function testTheBookCountsOfARowAreAskedInOneGo(): void
    {
        $series = $this->series('papa-calin', 'les-triados');
        $this->setId($series[0], 7);
        $this->setId($series[1], 9);

        $bookService = $this->createMock(BookServiceInterface::class);
        $bookService->expects($this->once())->method('countPublishedBySerie')->with([7, 9])->willReturn([7 => 38, 9 => 2]);

        $this->assertSame([7 => 38, 9 => 2], $this->extension(bookService: $bookService)->getSerieBookCounts($series));
    }

    // A serie no slug leads to draws nothing rather than falling back on the whole catalog of planches
    public function testABlockNamingNoExistingSerieDrawsNothing(): void
    {
        $serieService = $this->createStub(SerieServiceInterface::class);
        $serieService->method('findOneBySlugWithSortedBooks')->willReturn(null);

        $this->assertSame([], $this->extension(serieService: $serieService)->getSerieStrips('inconnue'));
    }
}
