<?php

namespace c975L\BookBundle\Controller;

use c975L\BookBundle\Service\SerieServiceInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SerieController extends AbstractController
{
    public function __construct(
        private readonly SerieServiceInterface $serieService
    ) {
    }

    // REDIRECT
    #[Route(
        '/serie/{slug}',
        name: 'serie_redirect',
        requirements: [
            'slug' => '^(mamievitevite)$'
        ],
        methods: ['GET']
    )]
    public function redirect_display(string $slug): Response
    {
        $slugs = [
            'mamievitevite' => 'mamie-vitevite',
        ];

        return $this->redirectToRoute('serie_display', ['slug' => $slugs[$slug]]);
    }

    // INDEX
    #[Route(
        '/series',
        name: 'serie_index',
        methods: ['GET']
    )]
    public function index(Request $request): Response
    {
        return $this->render(
            'serie/index.html.twig',
            ['series' => $this->serieService->findAllPaginated($request->query)]
        )->setMaxAge(3600);
    }

    // DISPLAY
    #[Route(
        '/serie/{slug}',
        name: 'serie_display',
        requirements: [
            'slug' => '^([a-z0-9\-]+)'
        ],
        methods: ['GET']
    )]
    public function display(string $slug): Response
    {
        $serie = $this->serieService->findOneBySlugWithSortedBooks($slug);
        $language = strpos($slug, 'english') !== false ? 'en' : 'fr';

        return $this->render(
            'serie/display.html.twig',
            [
                'language' => $language,
                'serie' => $serie
            ]
        )->setMaxAge(3600);
    }
}
