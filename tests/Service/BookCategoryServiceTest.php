<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Service;

use c975L\BookBundle\Entity\BookCategory;
use c975L\BookBundle\Repository\BookCategoryRepository;
use c975L\BookBundle\Service\BookCategoryService;
use c975L\UiBundle\Model\Pagination;
use c975L\UiBundle\Service\Paginator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

// What the categories' public pages read, the index being cut the same way the series' one is
class BookCategoryServiceTest extends TestCase
{
    private function category(string $slug): BookCategory
    {
        return new BookCategory()->setTitle(ucfirst($slug))->setSlug($slug);
    }

    private function createService(array $withBooks = [], array $all = [], ?BookCategory $bySlug = null): BookCategoryService
    {
        $repository = $this->createStub(BookCategoryRepository::class);
        $repository->method('findWithBooks')->willReturn($withBooks);
        $repository->method('findAll')->willReturn($all);
        $repository->method('findOneBy')->willReturn($bySlug);

        $requestStack = new RequestStack([new Request()]);

        return new BookCategoryService($repository, new Paginator($requestStack));
    }

    // Only the ones holding a book the catalog lists: an empty one heads a page with nothing under it
    public function testTheIndexReadsOnlyTheCategoriesHoldingABook(): void
    {
        $romans = $this->category('romans');
        $service = $this->createService(withBooks: [$romans]);

        $this->assertSame([$romans], $service->findWithBooks());
    }

    // Ten per page, the index growing on scroll from there
    public function testTheIndexIsCutIntoPages(): void
    {
        $categories = array_map(fn (int $i) => $this->category('rayon-' . $i), range(1, 23));
        $service = $this->createService(withBooks: $categories);

        $pagination = $service->findWithBooksPaginated(new InputBag());

        $this->assertInstanceOf(Pagination::class, $pagination);
        $this->assertCount(10, iterator_to_array($pagination));
        $this->assertSame(23, $pagination->getTotalItemCount());
        $this->assertSame(3, $pagination->getPageCount());
    }

    public function testASecondPageHoldsTheRowsTheFirstLeft(): void
    {
        $categories = array_map(fn (int $i) => $this->category('rayon-' . $i), range(1, 23));
        $service = $this->createService(withBooks: $categories);

        $pagination = $service->findWithBooksPaginated(new InputBag([Paginator::PAGE_PARAMETER => '3']));

        $this->assertCount(3, iterator_to_array($pagination));
        $this->assertSame(3, $pagination->getCurrentPageNumber());
    }

    // The trash included, the controller being what answers 410 on it rather than the read hiding it
    public function testASlugAnswersWithItsCategoryTrashOrNot(): void
    {
        $trashed = $this->category('romans')->setIsDeleted(true);

        $this->assertSame($trashed, $this->createService(bySlug: $trashed)->findOneBySlug('romans'));
        $this->assertNull($this->createService()->findOneBySlug('inconnu'));
    }

    public function testTheWholeListIsHandedOverAsTheRepositoryReadsIt(): void
    {
        $all = [$this->category('romans'), $this->category('albums')];

        $this->assertSame($all, $this->createService(all: $all)->findAll());
    }
}
