<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\BookCategory;
use c975L\UiBundle\Model\Pagination;
use Symfony\Component\HttpFoundation\InputBag;

interface BookCategoryServiceInterface
{
    /**
     * @param int|null $number caps the result, null returning them all
     *
     * @return BookCategory[]
     */
    public function findAll(?int $number = null): array;

    /**
     * @return BookCategory[] the categories holding at least one book the site shows
     */
    public function findWithBooks(): array;

    // What the categories' index lists, paginated.
    /** @return Pagination<BookCategory> */
    public function findWithBooksPaginated(InputBag $query): Pagination;

    // The category carrying that slug, whatever its state: one in the trash has to be found for its page to answer 410 rather than a plain 404. What it holds is read apart, through the catalog's own query (see BookServiceInterface::findPublishedByCategory()): a listing must not answer what the catalog itself doesn't show.
    public function findOneBySlug(string $slug): ?BookCategory;
}
