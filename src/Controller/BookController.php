<?php

namespace c975L\BookBundle\Controller;

use c975L\BookBundle\Routing\BookRoutePrefix;
use c975L\BookBundle\Service\BookServiceInterface;
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
    ) {
    }

    // INDEX
    #[Route(
        '/{books_prefix}',
        name: 'book_index',
        methods: ['GET'],
        condition: self::INDEX_CONDITION
    )]
    public function index(Request $request): Response
    {
        return $this->render(
            '@c975LBook/book/index.html.twig',
            ['books' => $this->bookService->findAllPaginated($request->query)]
        );
    }

    // DISPLAY
    // The slug is resolved here rather than by the router's own converter: an url naming no book falls back on the number it may carry instead of stopping at a 404 (see numberedRedirect())
    #[Route(
        '/{book_prefix}/{slug}',
        name: 'book_display',
        requirements: [
            'slug' => '^([a-z0-9\-]+)',
        ],
        methods: ['GET'],
        condition: self::DISPLAY_CONDITION
    )]
    public function display(string $slug): Response
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

        return $this->render(
            '@c975LBook/book/display.html.twig',
            [
                'book' => $book,
            ]
        );
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

        return $this->redirectToRoute('book_display', ['slug' => $book->getSlug()], Response::HTTP_MOVED_PERMANENTLY);
    }
}
