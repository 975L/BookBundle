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
use c975L\BookBundle\Service\ContributorServiceInterface;
use c975L\ConfigBundle\Service\LocalizedRouteNegotiator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\Routing\Attribute\Route;

// Same as BookController: the first segment is a ConfigBundle entry, empty when the people are not read here (see BookRoutePrefix)
class ContributorController extends AbstractController
{
    // The index and the page of one person are named apart, as the books' two are ("/auteurs" and "/auteur/{slug}"): a publisher names the list and the person differently, where the series share one word
    private const string CONTRIBUTORS_CONDITION = "service('" . BookRoutePrefix::ALIAS . "').matches('book-route-contributors', params['contributors_prefix'])";
    private const string CONTRIBUTOR_CONDITION = "service('" . BookRoutePrefix::ALIAS . "').matches('book-route-contributor', params['contributor_prefix'])";

    public function __construct(
        private readonly BookTranslatedLocales $translatedLocales,
        private readonly BookTranslator $bookTranslator,
        private readonly ContributorServiceInterface $contributorService,
        private readonly LocalizedRouteNegotiator $negotiator,
    ) {
    }

    // INDEX
    #[Route(
        '/{_locale}/{contributors_prefix}',
        name: 'contributor_index_localized',
        requirements: ['_locale' => '%c975l_config.locales_pattern%'],
        methods: ['GET'],
        condition: self::CONTRIBUTORS_CONDITION
    )]
    #[Route(
        '/{contributors_prefix}',
        name: 'contributor_index',
        methods: ['GET'],
        condition: self::CONTRIBUTORS_CONDITION
    )]
    // The people a shown book or serie still credits, and only those: someone recorded but credited nowhere yet would head a page with nothing under it (see ContributorRepository::findCredited())
    public function index(Request $request): Response
    {
        $askedLanguage = $this->negotiator->redirectToAskedLanguage($request, $this->translatedLocales->forIndex(), 'contributor_index');
        if (null !== $askedLanguage) {
            return $this->negotiator->vary($request, $askedLanguage);
        }

        $contributors = $this->contributorService->findCreditedPaginated($request->query);

        // The language being read laid over each presentation, for this render and no longer - a person's own name is never translated (see BookTranslator)
        $this->bookTranslator->apply($contributors);

        return $this->negotiator->vary($request, $this->render(
            '@c975LBook/contributor/index.html.twig',
            ['contributors' => $contributors]
        ));
    }

    // DISPLAY
    #[Route(
        '/{_locale}/{contributor_prefix}/{slug}',
        name: 'contributor_display_localized',
        requirements: [
            '_locale' => '%c975l_config.locales_pattern%',
            'slug' => '^([a-z0-9\-]+)',
        ],
        methods: ['GET'],
        condition: self::CONTRIBUTOR_CONDITION
    )]
    #[Route(
        '/{contributor_prefix}/{slug}',
        name: 'contributor_display',
        requirements: [
            'slug' => '^([a-z0-9\-]+)',
        ],
        methods: ['GET'],
        condition: self::CONTRIBUTOR_CONDITION
    )]
    public function display(string $slug, Request $request): Response
    {
        $contributor = $this->contributorService->findOneBySlugWithWorks($slug);

        // A slug nobody carries never was an url of ours: 404, as the {slug} of BookController answers by itself
        if (null === $contributor) {
            throw $this->createNotFoundException();
        }

        // Someone in the trash is off the site, and says so (see BookController::display() for why 410 and not 404)
        if ($contributor->isDeleted()) {
            throw new GoneHttpException();
        }

        // Someone set aside by their editor is off the site for as long as the box is ticked (see BookController::display() for why 404 and not 410)
        if ($contributor->isHidden()) {
            throw $this->createNotFoundException();
        }

        $locales = $this->translatedLocales->forEntry();

        // A localised url answers for every language the site declares: the guard stays as the one place that would refuse one (see BookTranslatedLocales)
        if (!$this->negotiator->isTranslated($request, $locales)) {
            throw $this->createNotFoundException();
        }

        $askedLanguage = $this->negotiator->redirectToAskedLanguage($request, $locales, 'contributor_display', ['slug' => $slug]);
        if (null !== $askedLanguage) {
            return $this->negotiator->vary($request, $askedLanguage);
        }

        // The person, the books and the series they are credited on, all read on this page (see BookTranslator::apply)
        $this->bookTranslator->apply([$contributor, ...$contributor->getBooks(), ...$contributor->getSeries()]);

        return $this->negotiator->vary($request, $this->render(
            '@c975LBook/contributor/display.html.twig',
            ['contributor' => $contributor]
        ));
    }
}
