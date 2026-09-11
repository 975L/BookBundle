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
use c975L\BookBundle\Service\BookTranslatedLocales;
use c975L\BookBundle\Service\BookTranslator;
use c975L\BookBundle\Service\SerieServiceInterface;
use c975L\BookBundle\Service\StripServiceInterface;
use c975L\ConfigBundle\Service\LocalizedRouteNegotiator;
use c975L\ConfigBundle\Service\LocalizedUrlGenerator;
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
        private readonly BookTranslatedLocales $translatedLocales,
        private readonly BookTranslator $bookTranslator,
        private readonly LocalizedRouteNegotiator $negotiator,
        private readonly LocalizedUrlGenerator $localizedUrlGenerator,
        private readonly SerieServiceInterface $serieService,
        private readonly StripServiceInterface $stripService,
    ) {
    }

    // The series telling planches, and not the planches themselves: a planche is read inside its serie, which lists them whole (see SerieController::display()). Listing them here as well served the same set at two addresses as soon as a site had but one such serie
    #[Route(
        '/{_locale}/{strips_prefix}',
        name: 'strip_index_localized',
        requirements: ['_locale' => '%c975l_config.locales_pattern%'],
        methods: ['GET'],
        condition: self::INDEX_CONDITION
    )]
    #[Route(
        '/{strips_prefix}',
        name: 'strip_index',
        methods: ['GET'],
        condition: self::INDEX_CONDITION
    )]
    public function index(Request $request): Response
    {
        $askedLanguage = $this->negotiator->redirectToAskedLanguage($request, $this->translatedLocales->forIndex(), 'strip_index');
        if (null !== $askedLanguage) {
            return $this->negotiator->vary($request, $askedLanguage);
        }

        $series = $this->serieService->findWithStripsPaginated($request->query);

        // The language being read laid over the titles and summaries, for this render and no longer (see BookTranslator::apply)
        $this->bookTranslator->apply($series);

        return $this->negotiator->vary($request, $this->render(
            '@c975LBook/strip/index.html.twig',
            ['series' => $series]
        ));
    }

    // The slug is resolved here rather than by the router's own converter: an url naming no planche falls back on the number it may carry instead of stopping at a 404 (see numberedRedirect())
    #[Route(
        '/{_locale}/{strip_prefix}/{slug}',
        name: 'strip_display_localized',
        requirements: [
            '_locale' => '%c975l_config.locales_pattern%',
            'slug' => '^([a-z0-9\-]+)',
        ],
        methods: ['GET'],
        condition: self::DISPLAY_CONDITION
    )]
    #[Route(
        '/{strip_prefix}/{slug}',
        name: 'strip_display',
        requirements: ['slug' => '^([a-z0-9\-]+)'],
        methods: ['GET'],
        condition: self::DISPLAY_CONDITION
    )]
    public function display(string $slug, Request $request): Response
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

        $locales = $this->translatedLocales->forEntry();

        // A localised url answers for every language the site declares: the guard stays as the one place that would refuse one (see BookTranslatedLocales)
        if (!$this->negotiator->isTranslated($request, $locales)) {
            throw $this->createNotFoundException();
        }

        $askedLanguage = $this->negotiator->redirectToAskedLanguage($request, $locales, 'strip_display', ['slug' => $slug]);
        if (null !== $askedLanguage) {
            return $this->negotiator->vary($request, $askedLanguage);
        }

        $previousNext = $this->stripService->findPreviousNext($strip);

        // The planche, the serie telling it, the people it names and the two planches beside it, all read on this page (see BookTranslator::apply)
        $this->bookTranslator->apply(array_filter([$strip, $strip->getSerie(), ...$strip->getCharacters(), $previousNext['previous'], $previousNext['next']]));

        return $this->negotiator->vary($request, $this->render(
            '@c975LBook/strip/display.html.twig',
            [
                'strip' => $strip,
                'previousNext' => $previousNext,
            ]
        ));
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

        // Through the generator rather than redirectToRoute(): a numbered url read at "/en/replique/003" leads to the planche's English page (see LocalizedUrlGenerator::path)
        return $this->redirect(
            $this->localizedUrlGenerator->path('strip_display', ['slug' => $strip->getSlug()]),
            Response::HTTP_MOVED_PERMANENTLY
        );
    }
}
