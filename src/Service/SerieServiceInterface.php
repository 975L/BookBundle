<?php

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Serie;
use c975L\UiBundle\Model\Pagination;
use Symfony\Component\HttpFoundation\InputBag;

interface SerieServiceInterface
{
    /**
     * @param int|null $number caps the result, null returning them all
     *
     * @return Serie[]
     */
    public function findAll(?int $number = null): array;

    /**
     * Every serie, 10 per page.
     *
     * @return Pagination<Serie>
     */
    public function findAllPaginated(InputBag $query): Pagination;

    /**
     * @return Serie[] at most $number, shuffled in PHP rather than by the query
     */
    public function findAllRandom(int $number): array;

    /**
     * @return Serie[] the series holding at least one book
     */
    public function findWithBooks(): array;

    /**
     * @return Serie[] the series holding at least one strip
     */
    public function findWithStrips(): array;

    // What the books' index lists, paginated.
    public function findWithBooksPaginated(InputBag $query): Pagination;

    // What the planches' index lists: the series telling them, paginated.
    public function findWithStripsPaginated(InputBag $query): Pagination;

    // The serie and its books, ordered by publication date with the undated ones first.
    public function findOneBySlugWithSortedBooks(string $slug): ?Serie;
}
