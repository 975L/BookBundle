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
use c975L\BookBundle\Service\BookTranslatedLocales;
use c975L\BookBundle\Service\BookTranslator;
use c975L\ConfigBundle\Service\LocalizedRouteNegotiator;
use c975L\UiBundle\Model\Pagination;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

        $controller = new BookCategoryController($categoryService, $bookService, $this->createStub(BookTranslatedLocales::class), $this->createStub(BookTranslator::class), $this->createNegotiator());

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
        $response = $this->createController($this->category(), [new Book()])->display('romans', new Request());

        $this->assertSame(200, $response->getStatusCode());
    }

    // A slug no category carries never was an url of ours
    public function testASlugNoCategoryCarriesIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController()->display('inconnu', new Request());
    }

    // In the trash is off the site and says so, where a crawler drops the page rather than coming back
    public function testACategoryInTheTrashIsGone(): void
    {
        $this->expectException(GoneHttpException::class);

        $this->createController($this->category(deleted: true))->display('romans', new Request());
    }

    // Set aside is not deleted: nothing is taken away, so the page simply is not there
    public function testACategorySetAsideIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController($this->category(hidden: true))->display('romans', new Request());
    }

    // The negotiator as a screen answering in one language alone meets it: nothing to refuse, nobody to move, and the response handed straight back
    private function createNegotiator(): LocalizedRouteNegotiator
    {
        $negotiator = $this->createStub(LocalizedRouteNegotiator::class);
        $negotiator->method('isTranslated')->willReturn(true);
        $negotiator->method('redirectToAskedLanguage')->willReturn(null);
        $negotiator->method('vary')->willReturnCallback(static fn (Request $request, Response $response): Response => $response);

        return $negotiator;
    }
}
