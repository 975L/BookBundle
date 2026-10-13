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
use c975L\BookBundle\Entity\Media;
use c975L\BookBundle\Routing\BookRoutePrefix;
use c975L\BookBundle\Service\BookServiceInterface;
use c975L\BookBundle\Service\BookTranslatedLocales;
use c975L\BookBundle\Twig\BookSectionsExtension;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\LocalizedRouteNegotiator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Routing\Attribute\Route;
use Vich\UploaderBundle\Templating\Helper\UploaderHelperInterface;

// The listening pages as data, for whatever plays them outside a browser: the very books ListenController lists and the very files its page hands the Reader, nothing more. Behind the same "book-route-listen" entry, so a site not serving the listening pages serves no JSON either
class ListenJsonController extends AbstractController
{
    public function __construct(
        private readonly BookServiceInterface $bookService,
        private readonly BookTranslatedLocales $translatedLocales,
        private readonly LocalizedRouteNegotiator $negotiator,
        private readonly ConfigServiceInterface $configService,
        private readonly Packages $packages,
        private readonly UrlHelper $urlHelper,
        private readonly UploaderHelperInterface $uploaderHelper,
    ) {
    }

    // INDEX. One answer holds every book and its files, a list of urls being light: the reader asks once, then plays with no further call. No redirection to the asked language: the unprefixed url answers in it, hence the Vary
    #[Route(
        '/{_locale}/{listen_prefix}.{_format}',
        name: 'book_listen_json_localized',
        requirements: ['_locale' => '%c975l_config.locales_pattern%', '_format' => 'json'],
        methods: ['GET'],
        condition: BookRoutePrefix::LISTEN_CONDITION
    )]
    #[Route(
        '/{listen_prefix}.{_format}',
        name: 'book_listen_json',
        requirements: ['_format' => 'json'],
        methods: ['GET'],
        condition: BookRoutePrefix::LISTEN_CONDITION
    )]
    public function index(Request $request): Response
    {
        if (!$this->negotiator->isTranslated($request, $this->translatedLocales->forIndex())) {
            throw $this->createNotFoundException();
        }

        // The rule of BookSectionsExtension::withAudio(), each book's recordings being read once: to tell whether it is listed, then to name the one played
        $books = [];
        foreach ($this->bookService->findAllPublished(null, $request->getLocale()) as $book) {
            $audios = BookSectionsExtension::audioMedias($book);
            if ([] !== $audios) {
                $books[] = $this->book($book, $audios);
            }
        }

        $response = new JsonResponse([
            'pageSound' => $this->configService->getBool($this->configService->get('book-page-sound'))
                ? $this->urlHelper->getAbsoluteUrl($this->packages->getUrl('bundles/c975lbook/sounds/page-turn.mp3'))
                : null,
            'books' => $books,
        ]);

        // Answers 304 to a reader already holding this very answer. On its content and not on the books' dates: a book taken off the list changes no date. Weak, the server's compression rewriting a strong one on its way out. Left private: the bare url also reads its language from the session, which no Vary tells a shared cache
        $response->setEtag(hash('xxh128', (string) $response->getContent()), true);
        $response->isNotModified($request);

        return $this->negotiator->vary($request, $response);
    }

    // What the listening page hands its player (see listen/display.html.twig): the pages and their timecodes go together or not at all, a few pages to leaf through before buying being no book to follow along
    /**
     * @param list<Media> $audios
     *
     * @return array<string, mixed>
     */
    private function book(Book $book, array $audios): array
    {
        // Mp3 first when there is one: the one format every player reads
        $audio = array_find($audios, static fn (Media $media): bool => 'audio/mpeg' === $media->getMimeType()) ?? $audios[0];

        // A page or the timecodes holding no file unfollows the whole book: the timecodes name the pages by their number, and a list with a hole would turn the wrong ones
        $pages = array_values(array_map($this->url(...), $book->getPages()->toArray()));
        $cues = $this->url($book->getCues());
        $followed = null !== $cues && [] !== $pages && !\in_array(null, $pages, true);

        return [
            'id' => $book->getId(),
            'slug' => $book->getSlug(),
            'title' => $book->getTitle(),
            'summary' => $book->getSummary(),
            'language' => $book->getLanguage(),
            'modification' => $book->getModification()?->format(\DATE_ATOM),
            'cover' => $this->url(BookSectionsExtension::cover($book)),
            'audio' => ['url' => $this->url($audio), 'type' => $audio->getMimeType()],
            'pages' => $followed ? $pages : [],
            'cues' => $followed ? $cues : null,
        ];
    }

    // Absolute, the reader not being a page of the site, and asked to the uploader as the listening page does (vich_uploader_asset()). Null for a row holding no file (see Media::validateFileOrAddress())
    private function url(?Media $media): ?string
    {
        $path = null === $media ? null : $this->uploaderHelper->asset($media);

        return null === $path ? null : $this->urlHelper->getAbsoluteUrl($path);
    }
}
