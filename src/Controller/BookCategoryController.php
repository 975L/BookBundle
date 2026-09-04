<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Controller;

use c975L\BookBundle\Routing\BookRoutePrefix;
use c975L\BookBundle\Service\BookCategoryServiceInterface;
use c975L\BookBundle\Service\BookServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\Routing\Attribute\Route;

// Same as SerieController: the first segment is a ConfigBundle entry, empty when the categories are not served here - which is how a site using them as plain tags shows them on its book pages without giving each one a page of its own (see BookRoutePrefix). The route names carry the bundle's own prefix where the other families don't: "category_display" is ShopBundle's, and a site running both bundles would have the two routes fighting over the name
class BookCategoryController extends AbstractController
{
    // The index of the categories and the page of one of them share the same first segment, the page sitting below the index
    private const string CATEGORIES_CONDITION = "service('" . BookRoutePrefix::ALIAS . "').matches('book-route-categories', params['categories_prefix'])";

    public function __construct(
        private readonly BookCategoryServiceInterface $categoryService,
        private readonly BookServiceInterface $bookService,
    ) {
    }

    // INDEX. Only the categories holding a book the site shows: an empty one heads a page with nothing under it, the same rule the series' index follows
    #[Route(
        '/{categories_prefix}',
        name: 'book_category_index',
        methods: ['GET'],
        condition: self::CATEGORIES_CONDITION
    )]
    public function index(Request $request): Response
    {
        return $this->render(
            '@c975LBook/category/index.html.twig',
            ['categories' => $this->categoryService->findWithBooksPaginated($request->query)]
        );
    }

    // DISPLAY
    #[Route(
        '/{categories_prefix}/{slug}',
        name: 'book_category_display',
        requirements: [
            'slug' => '^([a-z0-9\-]+)',
        ],
        methods: ['GET'],
        condition: self::CATEGORIES_CONDITION
    )]
    public function display(string $slug): Response
    {
        $category = $this->categoryService->findOneBySlug($slug);

        // A slug no category carries never was an url of ours
        if (null === $category) {
            throw $this->createNotFoundException();
        }

        // A category in the trash is off the site, and says so (see BookController::display() for why 410 and not 404)
        if ($category->isDeleted()) {
            throw new GoneHttpException();
        }

        // A category set aside by its editor is off the site for as long as the box is ticked (see BookController::display() for why 404 and not 410)
        if ($category->isHidden()) {
            throw $this->createNotFoundException();
        }

        return $this->render(
            '@c975LBook/category/display.html.twig',
            [
                'category' => $category,
                // Read through the catalog's own query rather than off the association: a category holds the books of a serie set aside too, which the catalog does not show (see BookRepository::findPublishedByCategory())
                'books' => $this->bookService->findPublishedByCategory($slug),
            ]
        );
    }
}
