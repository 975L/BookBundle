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
use c975L\BookBundle\Repository\BookCategoryRepository;
use c975L\UiBundle\Model\Pagination;
use c975L\UiBundle\Service\Paginator;
use Symfony\Component\HttpFoundation\InputBag;

class BookCategoryService implements BookCategoryServiceInterface
{
    public function __construct(
        private readonly BookCategoryRepository $categoryRepository,
        private readonly Paginator $paginator,
    ) {
    }

    // Finds all
    public function findAll(?int $number = null): array
    {
        return $this->categoryRepository->findAll($number);
    }

    // Finds the categories holding at least one book the site shows
    public function findWithBooks(): array
    {
        return $this->categoryRepository->findWithBooks();
    }

    // What the index lists, cut the same way the series' one is
    public function findWithBooksPaginated(InputBag $query): Pagination
    {
        return $this->paginator->paginate(
            $this->findWithBooks(),
            $this->paginator->getPage($query),
            10
        );
    }

    // Finds one by its slug, the trash included
    public function findOneBySlug(string $slug): ?BookCategory
    {
        return $this->categoryRepository->findOneBy(['slug' => $slug]);
    }
}
