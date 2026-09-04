<?php

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Book;
use c975L\UiBundle\Model\Pagination;
use Symfony\Component\HttpFoundation\InputBag;

interface BookServiceInterface
{
    /**
     * @return Book[] published or not
     */
    public function findAll(): array;

    // The published books, 10 per page.
    /** @return Pagination<Book> */
    public function findAllPaginated(InputBag $query): Pagination;

    /**
     * @param int|null    $number   caps the result, null returning them all
     * @param string|null $language narrows the list to one language, a book and its translation being two rows
     *
     * @return Book[]
     */
    public function findAllPublished(?int $number = null, ?string $language = null): array;

    // The catalog cut down to one category, empty where the slug names none.
    /** @return Book[] */
    public function findPublishedByCategory(string $slug, ?int $number = null): array;

    // Every book whose page answers, the ones a newer version replaces included - what a sitemap lists and what a link checker walks.
    /** @return Book[] */
    public function findAllOnline(): array;

    // How many books are out, without building a single one of them.
    /** @param string|null $language narrows the count to one language */
    public function countPublished(?string $language = null): int;

    // The languages the catalog is actually written in, so a list can be offered per language without a site declaring which ones it publishes.
    /** @return list<string> */
    public function findLanguages(): array;

    // Returns the book carrying that slug, null when none does.
    public function findOneBySlug(string $slug): ?Book;

    // Returns the book a number leads to, the one the catalog still lists, null when none does.
    public function findOneByNumber(int $number): ?Book;

    /**
     * @return Book[] the books not out yet - a publication date still ahead, or none set at all
     */
    public function findAllToBePublished(): array;

    // Matches published books on their title only.
    /** @return Book[] empty for an empty search */
    public function search(string $query, ?int $serieId = null): array;
}
