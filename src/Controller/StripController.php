<?php

namespace c975L\BookBundle\Controller;

use c975L\BookBundle\Routing\BookRoutePrefix;
use c975L\BookBundle\Service\SerieServiceInterface;
use c975L\BookBundle\Service\StripServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\Routing\Attribute\Route;

// Same as BookController: the first segment is a ConfigBundle entry, empty when the strips are not served here (see BookRoutePrefix)
class StripController extends AbstractController
{
    private const string INDEX_CONDITION = "service('" . BookRoutePrefix::ALIAS . "').matches('book-route-strips', params['strips_prefix'])";

    private const string DISPLAY_CONDITION = "service('" . BookRoutePrefix::ALIAS . "').matches('book-route-strip', params['strip_prefix'])";

    private const string SHORTCUT_CONDITION = "service('" . BookRoutePrefix::ALIAS . "').matches('book-route-strip-shortcut', params['strip_shortcut'])";

    public function __construct(
        private readonly SerieServiceInterface $serieService,
        private readonly StripServiceInterface $stripService,
    ) {
    }

    // The series telling planches, and not the planches themselves: a planche is read inside its serie, which lists them whole (see SerieController::display()). Listing them here as well served the same set at two addresses as soon as a site had but one such serie
    #[Route(
        '/{strips_prefix}',
        name: 'strip_index',
        methods: ['GET'],
        condition: self::INDEX_CONDITION
    )]
    public function index(Request $request): Response
    {
        return $this->render(
            '@c975LBook/strip/index.html.twig',
            ['series' => $this->serieService->findWithStripsPaginated($request->query)]
        );
    }

    // The slug is resolved here rather than by the router's own converter: an url naming no planche falls back on the number it may carry instead of stopping at a 404 (see numberedRedirect())
    #[Route(
        '/{strip_prefix}/{slug}',
        name: 'strip_display',
        requirements: ['slug' => '^([a-z0-9\-]+)'],
        methods: ['GET'],
        condition: self::DISPLAY_CONDITION
    )]
    public function display(string $slug): Response
    {
        $strip = $this->stripService->findOneBySlug($slug);

        // No planche carries that slug: it may be a numbered url of the site's former life ("/replique/003"), or one a reader was handed - the slug is tried first, so a planche actually named "003" is served rather than redirected
        if (null === $strip) {
            return $this->numberedRedirect($slug);
        }

        // A strip in the trash is off the site, and says so (see BookController::display() for why 410 and not 404)
        if ($strip->isDeleted()) {
            throw new GoneHttpException();
        }

        // A planche set aside by its editor is off the site for as long as the box is ticked (see BookController::display() for why 404 and not 410)
        if ($strip->isHidden()) {
            throw $this->createNotFoundException();
        }

        // A strip that has never been published is not publicly reachable
        if (null === $strip->getPublished()) {
            throw $this->createNotFoundException();
        }

        return $this->render(
            '@c975LBook/strip/display.html.twig',
            [
                'strip' => $strip,
                'previousNext' => $this->stripService->findPreviousNext($strip),
            ]
        );
    }

    // CAPTURE
    // The planche's card alone, framed square on the site's own background: what a reply is shared as, photographed from the very markup the page shows rather than drawn a second time by an image editor
    // A fixed segment and no ConfigBundle prefix: it is a tool page, not one of the site's own addresses - noindex below, and a headless browser is the only thing meant to open it (see the capture script shipped next to this bundle)
    #[Route(
        '/strip-card/{slug}',
        name: 'strip_card',
        requirements: ['slug' => '^([a-z0-9\-]+)'],
        methods: ['GET']
    )]
    public function card(string $slug): Response
    {
        $strip = $this->stripService->findOneBySlug($slug);

        // The same three gates as display(): what is not served there is not photographed here either
        if (null === $strip) {
            throw $this->createNotFoundException();
        }

        if ($strip->isDeleted()) {
            throw new GoneHttpException();
        }

        if ($strip->isHidden()) {
            throw $this->createNotFoundException();
        }

        if (null === $strip->getPublished()) {
            throw $this->createNotFoundException();
        }

        $response = $this->render('@c975LBook/strip/card.html.twig', ['strip' => $strip]);

        // The same card at a second address would otherwise read as the planche's page duplicated
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    // SHORTCUT
    // The short link a planche is shared with, one letter and its number ("/s3"): the letter is the site's own - "r" where the planches are replies - and left empty, no such url is served (see BookRoutePrefix)
    #[Route(
        '/{strip_shortcut}{number}',
        name: 'strip_shortcut',
        requirements: [
            'strip_shortcut' => '[a-z]{1,3}',
            'number' => '[0-9]{1,4}',
        ],
        methods: ['GET'],
        condition: self::SHORTCUT_CONDITION
    )]
    public function shortcut(int $number): Response
    {
        return $this->toNumber($number);
    }

    // What an url naming no planche is answered: the number it opens on leads to the planche carrying it, and anything else is a 404 as it always was (see BookController::numberedRedirect())
    private function numberedRedirect(string $slug): Response
    {
        $number = preg_match('/^([0-9]{1,4})(-|$)/', $slug, $matches) ? (int) $matches[1] : null;

        if (null === $number) {
            throw $this->createNotFoundException();
        }

        return $this->toNumber($number);
    }

    // 301 and not 302: the numbered url is the one indexed and shared (see BookController::toNumber())
    private function toNumber(int $number): Response
    {
        $strip = $this->stripService->findOneByNumber($number);

        if (null === $strip) {
            throw $this->createNotFoundException();
        }

        return $this->redirectToRoute('strip_display', ['slug' => $strip->getSlug()], Response::HTTP_MOVED_PERMANENTLY);
    }
}
