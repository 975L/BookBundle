<?php

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Book;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Symfony\Component\HttpFoundation\InputBag;

interface BookServiceInterface
{
    /**
     * @return Book|null null when no book carries that slug
     */
    public function findOneBySlug(string $slug);

    /**
     * @return Book[] published or not
     */
    public function findAll(): array;

    /**
     * The published books, 10 per page.
     *
     * @param InputBag $query the request's query bag, its "p" parameter holding the 1-based page number
     *
     * @return PaginationInterface<int, Book>
     */
    public function findAllPaginated($query);

    /**
     * @param int|null $number caps the result, null returning them all
     *
     * @return Book[]
     */
    public function findAllPublished(?int $number = null): array;

    /**
     * @return Book[] the books not out yet - a publication date still ahead, or none set at all
     */
    public function findAllToBePublished();

    /**
     * Matches published books on their title only.
     *
     * @return Book[] empty for an empty search
     */
    public function search(string $query);
}
