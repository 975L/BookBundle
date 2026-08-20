<?php

namespace c975L\BookBundle\Controller;

use c975L\BookBundle\Routing\BookRoutePrefix;
use c975L\BookBundle\Service\SerieServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// Same as BookController: the first segment is a ConfigBundle entry, empty when the series are not served here (see BookRoutePrefix)
class SerieController extends AbstractController
{
    private const string INDEX_CONDITION = "service('" . BookRoutePrefix::ALIAS . "').matches('book-route-series', params['series_prefix'])";

    private const string DISPLAY_CONDITION = "service('" . BookRoutePrefix::ALIAS . "').matches('book-route-serie', params['serie_prefix'])";

    public function __construct(
        private readonly SerieServiceInterface $serieService,
    ) {
    }

    // INDEX
    #[Route(
        '/{series_prefix}',
        name: 'serie_index',
        methods: ['GET'],
        condition: self::INDEX_CONDITION
    )]
    public function index(Request $request): Response
    {
        return $this->render(
            '@c975LBook/serie/index.html.twig',
            ['series' => $this->serieService->findAllPaginated($request->query)]
        )->setMaxAge(3600);
    }

    // DISPLAY
    #[Route(
        '/{serie_prefix}/{slug}',
        name: 'serie_display',
        requirements: [
            'slug' => '^([a-z0-9\-]+)',
        ],
        methods: ['GET'],
        condition: self::DISPLAY_CONDITION
    )]
    public function display(string $slug): Response
    {
        $serie = $this->serieService->findOneBySlugWithSortedBooks($slug);
        $language = str_contains($slug, 'english') ? 'en' : 'fr';

        return $this->render(
            '@c975LBook/serie/display.html.twig',
            [
                'language' => $language,
                'serie' => $serie,
            ]
        )->setMaxAge(3600);
    }
}
