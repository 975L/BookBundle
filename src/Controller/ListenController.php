<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Controller;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Routing\BookRoutePrefix;
use c975L\BookBundle\Service\BookServiceInterface;
use c975L\BookBundle\Service\BookTranslatedLocales;
use c975L\BookBundle\Service\BookTranslator;
use c975L\BookBundle\Twig\BookSectionsExtension;
use c975L\ConfigBundle\Service\LocalizedRouteNegotiator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\Routing\Attribute\Route;

// The books that can be listened to, and each one played: its recording, and its pages turning along it when the book carries their timecodes (see Book::getCues()). Same as SerieController: the index and the page share one first segment, a ConfigBundle entry left empty by default - a site turns the listening pages on by naming them (see BookRoutePrefix)
class ListenController extends AbstractController
{
    private const string LISTEN_CONDITION = "service('" . BookRoutePrefix::ALIAS . "').matches('book-route-listen', params['listen_prefix'])";

    public function __construct(
        private readonly BookServiceInterface $bookService,
        private readonly BookTranslatedLocales $translatedLocales,
        private readonly BookTranslator $bookTranslator,
        private readonly LocalizedRouteNegotiator $negotiator,
    ) {
    }

    // INDEX. The books written in the language being read, and only those holding a recording: a book and its translation are two rows, each with its own voice
    #[Route(
        '/{_locale}/{listen_prefix}',
        name: 'book_listen_index_localized',
        requirements: ['_locale' => '%c975l_config.locales_pattern%'],
        methods: ['GET'],
        condition: self::LISTEN_CONDITION
    )]
    #[Route(
        '/{listen_prefix}',
        name: 'book_listen_index',
        methods: ['GET'],
        condition: self::LISTEN_CONDITION
    )]
    public function index(Request $request): Response
    {
        $askedLanguage = $this->negotiator->redirectToAskedLanguage($request, $this->translatedLocales->forIndex(), 'book_listen_index');
        if (null !== $askedLanguage) {
            return $this->negotiator->vary($request, $askedLanguage);
        }

        $books = array_values(array_filter(
            $this->bookService->findAllPublished(null, $request->getLocale()),
            static fn (Book $book): bool => [] !== BookSectionsExtension::audioMedias($book)
        ));

        return $this->negotiator->vary($request, $this->render(
            '@c975LBook/listen/index.html.twig',
            ['books' => $books]
        ));
    }

    // DISPLAY. The same guards as the book's own page (see BookController::display()), plus one: a book with no recording has nothing to play here
    #[Route(
        '/{_locale}/{listen_prefix}/{slug}',
        name: 'book_listen_localized',
        requirements: [
            '_locale' => '%c975l_config.locales_pattern%',
            'slug' => '^([a-z0-9\-]+)',
        ],
        methods: ['GET'],
        condition: self::LISTEN_CONDITION
    )]
    #[Route(
        '/{listen_prefix}/{slug}',
        name: 'book_listen',
        requirements: [
            'slug' => '^([a-z0-9\-]+)',
        ],
        methods: ['GET'],
        condition: self::LISTEN_CONDITION
    )]
    public function display(string $slug, Request $request): Response
    {
        $book = $this->bookService->findOneBySlug($slug);

        if (null === $book) {
            throw $this->createNotFoundException();
        }

        // In the trash is off the site, and says so (see BookController::display() for why 410 and not 404)
        if ($book->isDeleted()) {
            throw new GoneHttpException();
        }

        // What the page will play, not what is filed as audio: a recording whose type is not "audio/*" would leave the player empty
        if ($book->isHidden() || [] === BookSectionsExtension::audioMedias($book)) {
            throw $this->createNotFoundException();
        }

        $locales = $this->translatedLocales->forEntry();
        if (!$this->negotiator->isTranslated($request, $locales)) {
            throw $this->createNotFoundException();
        }

        $askedLanguage = $this->negotiator->redirectToAskedLanguage($request, $locales, 'book_listen', ['slug' => $slug]);
        if (null !== $askedLanguage) {
            return $this->negotiator->vary($request, $askedLanguage);
        }

        $this->bookTranslator->apply([$book]);

        return $this->negotiator->vary($request, $this->render(
            '@c975LBook/listen/display.html.twig',
            ['book' => $book]
        ));
    }
}
