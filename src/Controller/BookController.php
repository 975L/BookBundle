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
use c975L\BookBundle\Service\BookServiceInterface;
use c975L\BookBundle\Service\BookTranslatedLocales;
use c975L\BookBundle\Service\BookTranslator;
use c975L\ConfigBundle\Service\LocalizedRouteNegotiator;
use c975L\ConfigBundle\Service\LocalizedUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\Routing\Attribute\Route;

// The first segment of each route is a ConfigBundle entry read at each request (see BookRoutePrefix): a site serves the catalog in its own language, and one that reads its books elsewhere leaves the entry empty, which takes these pages off the site altogether
class BookController extends AbstractController
{
    private const string INDEX_CONDITION = "service('" . BookRoutePrefix::ALIAS . "').matches('book-route-books', params['books_prefix'])";

    private const string DISPLAY_CONDITION = "service('" . BookRoutePrefix::ALIAS . "').matches('book-route-book', params['book_prefix'])";

    private const string SHORTCUT_CONDITION = "service('" . BookRoutePrefix::ALIAS . "').matches('book-route-book-shortcut', params['book_shortcut'])";

    public function __construct(
        private readonly BookServiceInterface $bookService,
        private readonly BookTranslatedLocales $translatedLocales,
        private readonly BookTranslator $bookTranslator,
        private readonly LocalizedRouteNegotiator $negotiator,
        private readonly LocalizedUrlGenerator $localizedUrlGenerator,
    ) {
    }

    // INDEX. The writing language keeps "/livres" byte for byte, the others go through "/{_locale}/livres" - a pattern matching nothing on a single-language site (see ConfigBundle's c975LConfigBundle::declareLocalesPattern())
    #[Route(
        '/{_locale}/{books_prefix}',
        name: 'book_index_localized',
        requirements: ['_locale' => '%c975l_config.locales_pattern%'],
        methods: ['GET'],
        condition: self::INDEX_CONDITION
    )]
    #[Route(
        '/{books_prefix}',
        name: 'book_index',
        methods: ['GET'],
        condition: self::INDEX_CONDITION
    )]
    public function index(Request $request): Response
    {
        $askedLanguage = $this->negotiator->redirectToAskedLanguage($request, $this->translatedLocales->forIndex(), 'book_index');
        if (null !== $askedLanguage) {
            return $this->negotiator->vary($request, $askedLanguage);
        }

        $books = $this->bookService->findAllPaginated($request->query);

        // The language being read laid over the titles and summaries, for this render and no longer: called here rather than on postLoad, the back office having to go on showing the text a row was written in (see BookTranslator::apply)
        $this->bookTranslator->apply($books);

        return $this->negotiator->vary($request, $this->render(
            '@c975LBook/book/index.html.twig',
            ['books' => $books]
        ));
    }

    // DISPLAY
    // The slug is resolved here rather than by the router's own converter: an url naming no book falls back on the number it may carry instead of stopping at a 404 (see numberedRedirect())
    #[Route(
        '/{_locale}/{book_prefix}/{slug}',
        name: 'book_display_localized',
        requirements: [
            '_locale' => '%c975l_config.locales_pattern%',
            'slug' => '^([a-z0-9\-]+)',
        ],
        methods: ['GET'],
        condition: self::DISPLAY_CONDITION
    )]
    #[Route(
        '/{book_prefix}/{slug}',
        name: 'book_display',
        requirements: [
            'slug' => '^([a-z0-9\-]+)',
        ],
        methods: ['GET'],
        condition: self::DISPLAY_CONDITION
    )]
    public function display(string $slug, Request $request): Response
    {
        $book = $this->bookService->findOneBySlug($slug);

        // No book carries that slug: it may be a numbered url of the site's former life, or one a reader was handed - the slug is tried first, so a book actually named "003-something" is served rather than redirected
        if (null === $book) {
            return $this->numberedRedirect($slug);
        }

        // A book in the trash is off the site, and says so: 410 rather than the 404 an url that never existed gets, which is what a search engine drops an indexed page on. It only lasts as long as the book can still be restored - removed for good, a "gone" Redirect takes over (see BookTrashManager::deletePermanently())
        if ($book->isDeleted()) {
            throw new GoneHttpException();
        }

        // A book set aside by its editor is off the site for as long as the box is ticked: 404 and not the 410 of the trash, nothing having been taken away - it is simply not shown (see Entity\Trait\HideableTrait)
        if ($book->isHidden()) {
            throw $this->createNotFoundException();
        }

        $locales = $this->translatedLocales->forEntry();

        // A localised url answers for every language the site declares: the guard stays as the one place that would refuse one, and refuses nothing while these screens are read in all of them (see BookTranslatedLocales)
        if (!$this->negotiator->isTranslated($request, $locales)) {
            throw $this->createNotFoundException();
        }

        $askedLanguage = $this->negotiator->redirectToAskedLanguage($request, $locales, 'book_display', ['slug' => $slug]);
        if (null !== $askedLanguage) {
            return $this->negotiator->vary($request, $askedLanguage);
        }

        // The book, the serie it belongs to, the categories it is filed under and the titles printed under its press cuttings and promotional visuals: all of them are read on this page, and all of them carry words of their own
        $this->bookTranslator->apply([$book, ...(null === $book->getSerie() ? [] : [$book->getSerie()]), ...$book->getShownCategories(), ...$book->getPresses(), ...$book->getMarketings()]);

        return $this->negotiator->vary($request, $this->render(
            '@c975LBook/book/display.html.twig',
            [
                'book' => $book,
            ]
        ));
    }

    // SHORTCUT
    // The short link a book is shared with, one letter and its number ("/b3"): the letter is the site's own - "h" where the books are stories - and left empty, no such url is served (see BookRoutePrefix)
    #[Route(
        '/{book_shortcut}{number}',
        name: 'book_shortcut',
        requirements: [
            'book_shortcut' => '[a-z]{1,3}',
            'number' => '[0-9]{1,4}',
        ],
        methods: ['GET'],
        condition: self::SHORTCUT_CONDITION
    )]
    public function shortcut(int $number): Response
    {
        return $this->toNumber($number);
    }

    // What an url naming no book is answered: the number it opens on leads to the book carrying it ("/livre/003", "/livre/003-le-tracteur", the forms a site served before it read its books by their slug alone), and anything else is a 404 as it always was
    private function numberedRedirect(string $slug): Response
    {
        $number = preg_match('/^([0-9]{1,4})(-|$)/', $slug, $matches) ? (int) $matches[1] : null;

        if (null === $number) {
            throw $this->createNotFoundException();
        }

        return $this->toNumber($number);
    }

    // 301 and not 302: the numbered url is the one indexed and shared, and a permanent answer is what hands its standing over to the slug it leads to
    private function toNumber(int $number): Response
    {
        $book = $this->bookService->findOneByNumber($number);

        if (null === $book) {
            throw $this->createNotFoundException();
        }

        // Through the generator rather than redirectToRoute(): a numbered url read at "/en/livre/003" leads to the book's English page, where the bare route would send the visitor back into the writing language (see LocalizedUrlGenerator::path)
        return $this->redirect(
            $this->localizedUrlGenerator->path('book_display', ['slug' => $book->getSlug()]),
            Response::HTTP_MOVED_PERMANENTLY
        );
    }
}
