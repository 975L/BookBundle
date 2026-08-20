<?php

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Symfony\Component\HttpFoundation\InputBag;

interface StripServiceInterface
{
    /**
     * @param int|null $number caps the result, null returning them all
     *
     * @return Strip[]
     */
    public function findAllPublished(?int $number = null): array;

    /**
     * The published strips, 24 per page.
     *
     * @param InputBag $query the request's query bag, its "p" parameter holding the 1-based page number
     *
     * @return PaginationInterface<int, Strip>
     */
    public function findAllPaginated($query);

    /**
     * @param int|null $number caps the result, null returning them all
     *
     * @return Strip[]
     */
    public function findAllPublishedBySerie(Serie $serie, ?int $number = null): array;

    /**
     * @param string $character the url slug of the character, as stored in the strip's slugged characters list
     *
     * @return Strip[] the strips whose characters list mentions $character
     */
    public function findAllByCharacter(string $character): array;

    /**
     * The published strips of a character, 24 per page.
     *
     * @param string   $character the url slug of the character
     * @param InputBag $query     the request's query bag, its "p" parameter holding the 1-based page number
     *
     * @return PaginationInterface<int, Strip>
     */
    public function findAllByCharacterPaginated(string $character, $query);

    /**
     * The strips surrounding $strip within its own serie, for the reader's prev/next links.
     *
     * @return array{previous: ?Strip, next: ?Strip} null on either side at the ends of the serie
     */
    public function findPreviousNext(Strip $strip): array;

    /**
     * Matches published strips on their title and characters.
     *
     * @return Strip[] empty for an empty search
     */
    public function search(string $query): array;
}
