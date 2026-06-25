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

    // REDIRECT
    #[Route(
        '/livre/{slug}',
        name: 'book_redirect',
        requirements: [
            'slug' => '^(le-repere|les-canadiennes|le-reveillon|trafic|chateau-hurlton|guerre-et-occupation)$'
        ],
        methods: ['GET']
    )]
    public function redirect_display(string $slug): Response
    {
        $slugs = [
            'le-reveillon' => 'mamie-vitevite-le-reveillon',
            'le-repere' => 'les-triados-trafic',
            'trafic' => 'les-triados-trafic',
            'chateau-hurlton' => 'la-guilde-des-seigneurs-chateau-hurlton',
            'guerre-et-occupation' => 'resistance-haute-savoie-guerre-et-occupation',
            'les-canadiennes' => 'resistance-haute-savoie-guerre-et-occupation',
        ];

        return $this->redirectToRoute('book_display', ['slug' => $slugs[$slug]]);
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
            'book/index.html.twig',
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
            'book/display.html.twig',
            [
                'book' => $book,
            ]
        )->setMaxAge(3600);
    }
}
