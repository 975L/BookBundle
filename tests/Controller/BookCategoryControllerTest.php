<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Controller;

use c975L\BookBundle\Controller\BookCategoryController;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookCategory;
use c975L\BookBundle\Service\BookCategoryServiceInterface;
use c975L\BookBundle\Service\BookServiceInterface;
use c975L\UiBundle\Model\Pagination;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

// A category's page answers what its row is: gone for the trash, missing for one set aside, and the catalog's own query for the books it lists
class BookCategoryControllerTest extends TestCase
{
    private function createController(?BookCategory $category = null, array $books = []): BookCategoryController
    {
        $categoryService = $this->createStub(BookCategoryServiceInterface::class);
        $categoryService->method('findOneBySlug')->willReturn($category);
        $categoryService->method('findWithBooksPaginated')->willReturn(new Pagination([], 1, 10, 0));

        $bookService = $this->createStub(BookServiceInterface::class);
        $bookService->method('findPublishedByCategory')->willReturn($books);

        $controller = new BookCategoryController($categoryService, $bookService);

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<page>');

        $container = new Container();
        $container->set('twig', $twig);
        $controller->setContainer($container);

        return $controller;
    }

    private function category(bool $hidden = false, bool $deleted = false): BookCategory
    {
        return new BookCategory()
            ->setTitle('Romans')
            ->setSlug('romans')
            ->setHidden($hidden)
            ->setIsDeleted($deleted)
        ;
    }

    public function testTheIndexListsTheCategoriesHoldingABook(): void
    {
        $this->assertSame(200, $this->createController()->index(new Request())->getStatusCode());
    }

    public function testACategoryStillShownAnswersWithItsPage(): void
    {
        $response = $this->createController($this->category(), [new Book()])->display('romans');

        $this->assertSame(200, $response->getStatusCode());
    }

    // A slug no category carries never was an url of ours
    public function testASlugNoCategoryCarriesIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController()->display('inconnu');
    }

    // In the trash is off the site and says so, where a crawler drops the page rather than coming back
    public function testACategoryInTheTrashIsGone(): void
    {
        $this->expectException(GoneHttpException::class);

        $this->createController($this->category(deleted: true))->display('romans');
    }

    // Set aside is not deleted: nothing is taken away, so the page simply is not there
    public function testACategorySetAsideIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController($this->category(hidden: true))->display('romans');
    }
}
