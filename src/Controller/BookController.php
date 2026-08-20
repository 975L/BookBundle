<?php

namespace c975L\BookBundle\Controller;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Routing\BookRoutePrefix;
use c975L\BookBundle\Service\BookServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// The first segment of each route is a ConfigBundle entry read at each request (see BookRoutePrefix): a site serves the catalog in its own language, and one that reads its books elsewhere leaves the entry empty, which takes these pages off the site altogether
class BookController extends AbstractController
{
    private const string INDEX_CONDITION = "service('" . BookRoutePrefix::ALIAS . "').matches('book-route-books', params['books_prefix'])";

    private const string DISPLAY_CONDITION = "service('" . BookRoutePrefix::ALIAS . "').matches('book-route-book', params['book_prefix'])";

    public function __construct(
        private readonly BookServiceInterface $bookService,
    ) {
    }

    // INDEX
    #[Route(
        '/{books_prefix}',
        name: 'book_index',
        methods: ['GET'],
        condition: self::INDEX_CONDITION
    )]
    public function index(Request $request): Response
    {
        return $this->render(
            '@c975LBook/book/index.html.twig',
            ['books' => $this->bookService->findAllPaginated($request->query)]
        )->setMaxAge(3600);
    }

    // DISPLAY
    #[Route(
        '/{book_prefix}/{slug:book}',
        name: 'book_display',
        requirements: [
            'slug' => '^([a-z0-9\-]+)',
        ],
        methods: ['GET'],
        condition: self::DISPLAY_CONDITION
    )]
    public function display(Book $book): Response
    {
        return $this->render(
            '@c975LBook/book/display.html.twig',
            [
                'book' => $book,
            ]
        )->setMaxAge(3600);
    }
}
