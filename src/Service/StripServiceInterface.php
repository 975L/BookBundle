<?php

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\UiBundle\Model\Pagination;
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
     * @param int|null $number caps the result, null returning them all
     *
     * @return Strip[]
     */
    public function findAllPublishedBySerie(Serie $serie, ?int $number = null, ?string $character = null): array;

    /**
     * The characters speaking in one serie, each named once and sorted by name - the chips a serie's own listing offers to filter on.
     *
     * @return array<int, array{name: string, slug: string}>
     */
    public function findCharactersBySerie(Serie $serie): array;

    /**
     * @return Strip[] the strips whose characters list mentions $character
     */
    public function findAllByCharacter(string $character): array;

    /**
     * The planches of one serie, 24 per page: a serie's own page lists them whole, in the order it tells them, rather than showing a handful (see serie/display.html.twig, which grows the list as the visitor scrolls).
     *
     * @return Pagination<Strip>
     */
    public function findAllBySeriePaginated(Serie $serie, InputBag $query, ?string $character = null): Pagination;

    /**
     * The strips surrounding $strip within its own serie, for the reader's prev/next links.
     *
     * @return array{previous: ?Strip, next: ?Strip} null on either side at the ends of the serie
     */
    /**
     * Returns the planche carrying that slug, null when none does.
     */
    public function findOneBySlug(string $slug): ?Strip;

    /**
     * Returns the planche a number leads to, null when none does.
     */
    public function findOneByNumber(int $number): ?Strip;

    public function findPreviousNext(Strip $strip): array;

    /**
     * Matches published strips on their title, their characters and their summary - within one serie when $serieId is given, which is what a serie's own page searches.
     *
     * @return Strip[] empty for an empty search
     */
    public function search(string $query, ?int $serieId = null): array;
}
