<?php

namespace c975L\BookBundle\Controller;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Service\BookServiceInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class BookController extends AbstractController
{
    public function __construct(
        private readonly BookServiceInterface $bookService
    ) {
    }

    // INDEX
    #[Route(
        '/livres',
        name: 'book_index',
        methods: ['GET']
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
        '/livre/{slug:book}',
        name: 'book_display',
        requirements: [
            'slug' => '^([a-z0-9\-]+)'
        ],
        methods: ['GET']
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
