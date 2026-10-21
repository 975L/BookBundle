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
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Routing\BookRoutePrefix;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use c975L\BookBundle\Service\BookFavoriteItemProvider;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\BookServiceInterface;
use c975L\BookBundle\Service\BookTranslatedLocales;
use c975L\BookBundle\Service\BookTranslator;
use c975L\BookBundle\Twig\BookPlaysExtension;
use c975L\BookBundle\Twig\BookSectionsExtension;
use c975L\BookBundle\Twig\BookTranslationExtension;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\LocalizedRouteNegotiator;
use c975L\UiBundle\Service\PlayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingExceptionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
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
        private readonly PlayService $playService,
        private readonly BookCustomizationRegistry $customizationRegistry,
        private readonly TranslatorInterface $translator,
        private readonly BookTranslator $bookTranslator,
        private readonly BookTranslationExtension $translationExtension,
        private readonly BookRepository $bookRepository,
        private readonly BookPlaysExtension $playsExtension,
        private readonly BookPublicUrlResolver $publicUrlResolver,
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
        $listed = [];
        foreach ($this->bookService->findAllPublished(null, $request->getLocale()) as $book) {
            $audios = BookSectionsExtension::audioMedias($book);
            if ([] !== $audios) {
                $listed[] = [$book, $audios];
            }
        }

        // Their credits, videos and series read for the whole list, the series in the asked language as the book's own page shows them
        $listedBooks = array_map(static fn (array $entry): Book => $entry[0], $listed);
        $this->bookRepository->withCreditsAndVideos($listedBooks);
        $series = [];
        foreach ($listedBooks as $book) {
            if (null !== $book->getSerie()) {
                $series[spl_object_id($book->getSerie())] = $book->getSerie();
            }
        }
        $this->bookTranslator->apply($series);

        // How many times each was heard, on the site and in its apps, read in one go - and said as the book's pages say it, null below the site's minimum (see BookPlaysExtension::shown())
        $plays = $this->playService->totals(BookFavoriteItemProvider::OWNER_TYPE, array_map(static fn (Book $book): int => (int) $book->getId(), $listedBooks));

        $books = [];
        foreach ($listed as [$book, $audios]) {
            // The roles named in the language the listening page names them in (see book_ui_locale())
            $books[] = [...$this->book($book, $audios, $this->playsExtension->shown($plays[(int) $book->getId()] ?? 0), $this->translationExtension->uiLocale($book->getLanguage()) ?? $request->getLocale()), 'page' => $this->page($book)];
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

    // What the listening page hands its player (see listen/display.html.twig): the pages and their timecodes go together or not at all, a few pages to leaf through before buying being no book to follow along. Then what the book's own page says of it - its fourth cover, its informations, its videos
    /**
     * @param list<Media> $audios
     *
     * @return array<string, mixed>
     */
    private function book(Book $book, array $audios, ?int $plays, string $locale): array
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
            'coverBack' => $this->url(BookSectionsExtension::backCover($book)),
            'audio' => ['url' => $this->url($audio), 'type' => $audio->getMimeType()],
            'pages' => $followed ? $pages : [],
            'cues' => $followed ? $cues : null,
            'published' => $book->getPublished()?->format(\DATE_ATOM),
            'age' => $book->getAge(),
            'serie' => null === $book->getSerie() ? null : ['title' => $book->getSerie()->getTitle(), 'number' => $book->getNumber()],
            'credits' => $this->credits($book, $locale),
            'videos' => $this->videos($book),
            'plays' => $plays,
            'data' => $this->data($book),
        ];
    }

    // The fields of the site's own a book carries (see Book::$data) that the site lists in "book-listen-data" - what its pages already print, "idea" for the child a story was written for. None by default: those fields are the site's, and nothing says they are all meant to be read
    /** @return array<string, scalar> */
    private function data(Book $book): array
    {
        $keys = array_filter(array_map(trim(...), explode(',', (string) $this->configService->get('book-listen-data'))));

        return array_filter(
            array_intersect_key($book->getData(), array_flip($keys)),
            static fn (mixed $value): bool => \is_scalar($value) && '' !== $value
        );
    }

    // Who made the book, each with the part they took, as its informations list them (see components/Book/Informations.html.twig): the author and the illustrator, the serie's standing for a book naming none of its own, then everyone else it credits
    /** @return list<array{role: string, name: string}> */
    private function credits(Book $book, string $locale): array
    {
        $credits = [];
        foreach (['label.author' => $book->getEffectiveAuthor(), 'label.illustrator' => $book->getEffectiveIllustrator()] as $label => $contributor) {
            if (null !== $contributor) {
                $credits[] = ['role' => $this->translator->trans($label, [], 'book', $locale), 'name' => (string) $contributor->getName()];
            }
        }
        foreach ($book->getContributors() as $credit) {
            if (null !== $credit->getContributor()) {
                $credits[] = ['role' => $this->customizationRegistry->getRoleLabel((string) $credit->getRole(), $locale), 'name' => (string) $credit->getContributor()->getName()];
            }
        }

        return $credits;
    }

    // The book's videos, wherever each is held: the YouTube player's address as stored (already its privacy-respecting form, see BookVideoNoCookieListener), or the uploaded file's
    /** @return list<array{title: ?string, youtube: ?string, url: ?string}> */
    private function videos(Book $book): array
    {
        $videos = [];
        foreach ($book->getVideos() as $video) {
            $youtube = $video->getYoutubeUrl();
            $url = null === $youtube ? $this->url($video) : null;
            if (null !== $youtube || null !== $url) {
                $videos[] = ['title' => $video->getTitle(), 'youtube' => $youtube, 'url' => $url];
            }
        }

        return $videos;
    }

    // The book's own page in the language asked, absolute like the files - null where the site serves no book pages (see BookPublicUrlResolver::resolveLocalizedPath()), and null for a book whose url cannot be built rather than no answer for the others
    private function page(Book $book): ?string
    {
        try {
            $path = $this->publicUrlResolver->resolveLocalizedPath('book_display', ['slug' => $book->getSlug()]);
        } catch (RoutingExceptionInterface) {
            return null;
        }

        return null === $path ? null : $this->urlHelper->getAbsoluteUrl($path);
    }

    // Absolute, the reader not being a page of the site, and asked to the uploader as the listening page does (vich_uploader_asset()). Null for a row holding no file (see Media::validateFileOrAddress())
    private function url(?Media $media): ?string
    {
        $path = null === $media ? null : $this->uploaderHelper->asset($media);

        return null === $path ? null : $this->urlHelper->getAbsoluteUrl($path);
    }
}
