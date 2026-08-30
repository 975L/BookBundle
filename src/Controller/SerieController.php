<?php

namespace c975L\BookBundle\Controller;

use c975L\BookBundle\Routing\BookRoutePrefix;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\SerieServiceInterface;
use c975L\BookBundle\Service\StripServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\Routing\Attribute\Route;

// Same as BookController: the first segment is a ConfigBundle entry, empty when the series are not served here (see BookRoutePrefix)
class SerieController extends AbstractController
{
    // The index of the series of books and the page of one of them share the same first segment, the page sitting below the index
    private const string SERIES_CONDITION = "service('" . BookRoutePrefix::ALIAS . "').matches('book-route-series', params['series_prefix'])";

    // A serie telling planches is read below the planches' index instead, that being the one listing it (see StripController::index())
    private const string STRIPS_CONDITION = "service('" . BookRoutePrefix::ALIAS . "').matches('book-route-strips', params['strips_prefix'])";

    public function __construct(
        private readonly SerieServiceInterface $serieService,
        private readonly StripServiceInterface $stripService,
    ) {
    }

    // INDEX
    #[Route(
        '/{series_prefix}',
        name: 'serie_index',
        methods: ['GET'],
        condition: self::SERIES_CONDITION
    )]
    // The series of books, and only those: the ones telling planches are listed by StripController::index(), so neither index repeats the other (see SerieKind)
    public function index(Request $request): Response
    {
        return $this->render(
            '@c975LBook/serie/index.html.twig',
            ['series' => $this->serieService->findWithBooksPaginated($request->query)]
        );
    }

    // DISPLAY
    // Two routes for one page: a serie is read below the index listing it - "/series/{slug}" for the ones telling books, "/strips/{slug}" for the ones telling planches (see SerieKind). A segment of its own would have filed both under a word neither index wears, and search engines read the hierarchy the url draws
    #[Route(
        '/{series_prefix}/{slug}',
        name: 'serie_display',
        requirements: [
            'slug' => '^([a-z0-9\-]+)',
        ],
        methods: ['GET'],
        condition: self::SERIES_CONDITION
    )]
    #[Route(
        '/{strips_prefix}/{slug}',
        name: 'strip_serie_display',
        requirements: [
            'slug' => '^([a-z0-9\-]+)',
        ],
        methods: ['GET'],
        condition: self::STRIPS_CONDITION
    )]
    public function display(string $slug, Request $request): Response
    {
        // Who speaks, when the visitor clicked a chip: the serie's listing narrows down to them rather than leading to a page apart (see Strip:Characters)
        $character = trim((string) $request->query->get('character')) ?: null;

        $serie = $this->serieService->findOneBySlugWithSortedBooks($slug);

        // A slug no serie carries never was an url of ours: 404, as the {slug:book} of BookController answers by itself
        if (null === $serie) {
            throw $this->createNotFoundException();
        }

        // A serie in the trash is off the site, and says so (see BookController::display() for why 410 and not 404)
        if ($serie->isDeleted()) {
            throw new GoneHttpException();
        }

        // A serie set aside by its editor is off the site for as long as the box is ticked (see BookController::display() for why 404 and not 410)
        if ($serie->isHidden()) {
            throw $this->createNotFoundException();
        }

        // A serie has but one address, the one its kind wears: asked under the other index' segment, it points there once and for all rather than answering the same page at two urls competing for the same search result
        $route = BookPublicUrlResolver::serieRoute($serie);
        if ($route !== $request->attributes->get('_route')) {
            return $this->redirectToRoute($route, ['slug' => $slug], Response::HTTP_MOVED_PERMANENTLY);
        }

        return $this->render(
            '@c975LBook/serie/display.html.twig',
            [
                'serie' => $serie,
                // The planches it tells, page by page: the page lists them whole and grows as the visitor scrolls, where the books it holds are few enough to be shown at once
                'strips' => $this->stripService->findAllBySeriePaginated($serie, $request->query, $character),
                // Who speaks in this serie: the same chips as under a planche, below the search field
                'characters' => $this->stripService->findCharactersBySerie($serie),
                'character' => $character,
            ]
        );
    }
}
