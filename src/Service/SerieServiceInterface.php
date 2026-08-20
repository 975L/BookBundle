<?php

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Serie;
use Knp\Component\Pager\Pagination\PaginationInterface;
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
     * @param InputBag $query the request's query bag, its "p" parameter holding the 1-based page number
     *
     * @return PaginationInterface<int, Serie>
     */
    public function findAllPaginated($query);

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

    // The serie and its books, ordered by publication date with the undated ones first.
    public function findOneBySlugWithSortedBooks(string $slug): ?Serie;
}
