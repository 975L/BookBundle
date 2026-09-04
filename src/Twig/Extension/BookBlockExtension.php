<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Twig\Extension;

use c975L\BookBundle\Service\BookCategoryServiceInterface;
use c975L\BookBundle\Service\BookServiceInterface;
use c975L\BookBundle\Service\ContributorServiceInterface;
use c975L\BookBundle\Service\SerieServiceInterface;
use c975L\BookBundle\Service\StripServiceInterface;
use Twig\Attribute\AsTwigFunction;

class BookBlockExtension
{
    public function __construct(
        private readonly BookCategoryServiceInterface $categoryService,
        private readonly BookServiceInterface $bookService,
        private readonly ContributorServiceInterface $contributorService,
        private readonly SerieServiceInterface $serieService,
        private readonly StripServiceInterface $stripService,
    ) {
    }

    // Shuffling happens before the maximum applies, so "4 series at random" draws from the whole catalog and not from its first four - the maximum is then applied here rather than by the query, which would have cut the catalog down before the draw. A block asking for it declines its own cache entry (see BookBlockCacheTagProvider), so a new draw is made at every render
    #[AsTwigFunction('book_block_series')]
    public function getSeries(?int $max = null, bool $random = false): array
    {
        return $random ? $this->draw($this->serieService->findAll(), $max) : $this->serieService->findAll($max);
    }

    // Only the categories holding a book the site shows, as their own index lists them: an empty one would head a card saying "0"
    #[AsTwigFunction('book_block_categories')]
    public function getCategories(?int $max = null, bool $random = false): array
    {
        return $this->draw($this->categoryService->findWithBooks(), $max, $random);
    }

    // Narrowed down to one category when the block names one, the whole catalog otherwise - the slug is what the block stores, its data being JSON and holding no entity (see BooksBlockType)
    #[AsTwigFunction('book_block_books')]
    public function getBooks(?int $max = null, bool $random = false, ?string $categorySlug = null): array
    {
        // A draw reads the whole catalog before cutting it down, for the reason getSeries() states above: the maximum is then applied by draw() and not by the query
        $number = $random ? null : $max;

        $books = null === $categorySlug || '' === $categorySlug
            ? $this->bookService->findAllPublished($number)
            : $this->bookService->findPublishedByCategory($categorySlug, $number);

        return $random ? $this->draw($books, $max) : $books;
    }

    // The whole list is read whatever the maximum: findCredited() takes none, the people a catalog credits being a handful and not a shelf
    #[AsTwigFunction('book_block_contributors')]
    public function getContributors(?int $max = null, bool $random = false): array
    {
        return $this->draw($this->contributorService->findCredited(), $max, $random);
    }

    // Same as the contributors: findAllToBePublished() reads the books waiting for their date, which are counted in units, and the maximum cuts that list here
    #[AsTwigFunction('book_block_to_be_published')]
    public function getToBePublished(?int $max = null, bool $random = false): array
    {
        return $this->draw($this->bookService->findAllToBePublished(), $max, $random);
    }

    #[AsTwigFunction('book_block_serie_strips')]
    public function getSerieStrips(string $serieSlug, ?int $max = null, bool $random = false): array
    {
        $serie = $this->serieService->findOneBySlugWithSortedBooks($serieSlug);
        if (!$serie) {
            return [];
        }

        return $random
            ? $this->draw($this->stripService->findAllPublishedBySerie($serie), $max)
            : $this->stripService->findAllPublishedBySerie($serie, $max);
    }

    // The rows a listing keeps out of the list it was handed: drawn at random when the block asks for it, taken in the catalog's own order otherwise
    private function draw(array $rows, ?int $max, bool $random = true): array
    {
        if ($random) {
            shuffle($rows);
        }

        return null !== $max ? \array_slice($rows, 0, $max) : $rows;
    }
}
