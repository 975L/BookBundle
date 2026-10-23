<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Controller;

use c975L\BookBundle\Controller\ListenJsonController;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookContributor;
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Entity\BookVideo;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Media;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\BookServiceInterface;
use c975L\BookBundle\Service\BookTranslatedLocales;
use c975L\BookBundle\Service\BookTranslator;
use c975L\BookBundle\Twig\BookPlaysExtension;
use c975L\BookBundle\Twig\BookTranslationExtension;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\LocalizedRouteNegotiator;
use c975L\UiBundle\Service\PlayService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelperInterface;

// The listening pages as data hand out what the listening page plays, under absolute urls, and nothing a book does not carry
class ListenJsonControllerTest extends TestCase
{
    /**
     * @param Book[] $published
     */
    private function createController(array $published = [], bool $translated = true, bool $pageSound = false, string $uriPrefix = '/', ?BookTranslator $bookTranslator = null, string $locale = '', string $playsMinimum = '', bool $bookPages = true): ListenJsonController
    {
        $bookService = $this->createStub(BookServiceInterface::class);
        $bookService->method('findAllPublished')->willReturn($published);

        $negotiator = $this->createStub(LocalizedRouteNegotiator::class);
        $negotiator->method('isTranslated')->willReturn($translated);
        $negotiator->method('vary')->willReturnCallback(static fn (Request $request, Response $response): Response => $response);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $slug): mixed => match ($slug) {
            'book-listen-data' => 'idea, age',
            'book-plays-minimum' => $playsMinimum,
            default => $pageSound,
        });
        $configService->method('getBool')->willReturnCallback(static fn (mixed $value): bool => (bool) $value);

        $packages = $this->createStub(Packages::class);
        $packages->method('getUrl')->willReturnCallback(static fn (string $path): string => '/' . $path);

        // A real one, the class being final: it reads the host off the request being answered
        $request = $this->request();
        $request->attributes->set('_locale', $locale);
        $requestStack = new RequestStack([$request]);

        // What the mapping answers: its uri_prefix before the file's name, and nothing for a row holding no file
        $uploaderHelper = $this->createStub(UploaderHelperInterface::class);
        $uploaderHelper->method('asset')->willReturnCallback(static fn (Media $media): ?string => null === $media->getName() ? null : $uriPrefix . $media->getName());

        // Every book heard three times, on the site and in its apps
        $playService = $this->createStub(PlayService::class);
        $playService->method('totals')->willReturnCallback(static fn (string $ownerType, array $ids): array => array_fill_keys($ids, 3));

        $registry = $this->createStub(BookCustomizationRegistry::class);
        $registry->method('getRoleLabel')->willReturnCallback(static fn (string $role): string => 'Idée : ' . $role);

        // The book's page under the books' prefix, below the language asked unless it is the writing one, none at all for a site not serving them
        $publicUrlResolver = $this->createStub(BookPublicUrlResolver::class);
        $publicUrlResolver->method('resolveLocalizedPath')->willReturnCallback(static fn (string $route, array $parameters): ?string => $bookPages ? '/livre/' . (1 === preg_match('/^[a-z0-9\-]+$/', $parameters['slug']) ? $parameters['slug'] : throw new InvalidParameterException('slug')) : null);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key, array $parameters, ?string $domain, ?string $locale): string => 'fr' === $locale ? $key : $locale . ':' . $key);

        $controller = new ListenJsonController($bookService, $this->createStub(BookTranslatedLocales::class), $negotiator, $configService, $packages, new UrlHelper($requestStack), $uploaderHelper, $playService, $registry, $translator, $bookTranslator ?? $this->createStub(BookTranslator::class), new BookTranslationExtension($requestStack), $this->createStub(BookRepository::class), new BookPlaysExtension($playService, $configService), $publicUrlResolver);
        // The play routes under their full address, as UiBundle declares them
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $route, array $parameters): string => 'https://example.com/' . ('ui_play_start' === $route ? 'play-start' : 'play') . '/' . $parameters['ownerType'] . '/' . $parameters['ownerId']);
        $container = new Container();
        $container->set('router', $router);
        $controller->setContainer($container);

        return $controller;
    }

    private function book(bool $audio = true, bool $pages = false, bool $cues = false): Book
    {
        $book = new Book()
            ->setTitle('La Moto et la Biche')
            ->setSlug('la-moto-et-la-biche')
            ->setLanguage('fr')
        ;
        if ($audio) {
            $book->addMedia(new BookMedia()->setKind('audio_mp3')->setName('medias/histoire.mp3'));
        }
        if ($pages) {
            $book->addMedia(new BookMedia()->setKind('page')->setName('medias/page-1.webp'));
            $book->addMedia(new BookMedia()->setKind('page')->setName('medias/page-2.webp'));
        }
        if ($cues) {
            $book->addMedia(new BookMedia()->setKind('cues')->setName('medias/histoire.vtt'));
        }

        return $book;
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    // Read in the writing language, as the bare url negotiates it
    private function request(): Request
    {
        $request = Request::create('https://example.com/ecouter.json');
        $request->setLocale('fr');

        return $request;
    }

    // A book with no recording has nothing to play: it is left out, as on the listening index
    public function testListsOnlyTheBooksHoldingARecording(): void
    {
        $response = $this->createController([$this->book(), $this->book(audio: false)])->index($this->request());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $this->data($response)['books']);
    }

    // The reader is no page of the site: every file comes under its full address
    public function testGivesTheFilesUnderAbsoluteUrls(): void
    {
        $book = $this->data($this->createController([$this->book(pages: true, cues: true)])->index($this->request()))['books'][0];

        $this->assertSame('la-moto-et-la-biche', $book['slug']);
        $this->assertSame('La Moto et la Biche', $book['title']);
        $this->assertSame('fr', $book['language']);
        $this->assertSame(['url' => 'https://example.com/medias/histoire.mp3', 'type' => 'audio/mpeg'], $book['audio']);
        $this->assertSame(['https://example.com/medias/page-1.webp', 'https://example.com/medias/page-2.webp'], $book['pages']);
        $this->assertSame('https://example.com/medias/histoire.vtt', $book['cues']);
    }

    // An app counts its listens as the listening page does: a ticket taken where the JSON says, handed back where it says
    public function testGivesWhereAListenIsCounted(): void
    {
        $book = $this->book();
        new \ReflectionProperty(Book::class, 'id')->setValue($book, 12);
        $data = $this->data($this->createController([$book])->index($this->request()))['books'][0];

        $this->assertSame('https://example.com/play-start/book/12', $data['playStart']);
        $this->assertSame('https://example.com/play/book/12', $data['play']);
    }

    // Pages with no timecodes are a few to leaf through before buying, not a book to follow along: they stay on the site
    public function testKeepsThePagesOfABookWithNoTimecodes(): void
    {
        $book = $this->data($this->createController([$this->book(pages: true)])->index($this->request()))['books'][0];

        $this->assertSame([], $book['pages']);
        $this->assertNull($book['cues']);
    }

    // The one format every player reads comes first, and any other recording stands in for want of it
    public function testPrefersTheMp3AmongTheRecordings(): void
    {
        $both = $this->book(audio: false)
            ->addMedia(new BookMedia()->setKind('audio_m4a')->setName('medias/histoire.m4a'))
            ->addMedia(new BookMedia()->setKind('audio_mp3')->setName('medias/histoire.mp3'))
        ;
        $m4aOnly = $this->book(audio: false)->addMedia(new BookMedia()->setKind('audio_m4a')->setName('medias/histoire.m4a'));

        $books = $this->data($this->createController([$both, $m4aOnly])->index($this->request()))['books'];

        $this->assertSame(['url' => 'https://example.com/medias/histoire.mp3', 'type' => 'audio/mpeg'], $books[0]['audio']);
        $this->assertSame(['url' => 'https://example.com/medias/histoire.m4a', 'type' => 'audio/mp4'], $books[1]['audio']);
    }

    // The fourth cover is the book's own upload, never a page standing in for it
    public function testNamesTheFourthCoverOrNothing(): void
    {
        $covered = $this->book()->addMedia(new BookMedia()->setKind('cover_back')->setName('medias/quatrieme.webp'));

        $books = $this->data($this->createController([$covered, $this->book(pages: true)])->index($this->request()))['books'];

        $this->assertSame('https://example.com/medias/quatrieme.webp', $books[0]['coverBack']);
        $this->assertNull($books[1]['coverBack']);
    }

    // The book's own page under its full address, and none for a site whose book pages are read elsewhere
    public function testGivesTheBooksPageOrNothing(): void
    {
        $this->assertSame('https://example.com/livre/la-moto-et-la-biche', $this->data($this->createController([$this->book()])->index($this->request()))['books'][0]['page']);
        $this->assertNull($this->data($this->createController([$this->book()], bookPages: false)->index($this->request()))['books'][0]['page']);
    }

    // A slug the route refuses leaves that book without a page rather than every book without an answer
    public function testABookWhoseUrlCannotBeBuiltHasNoPage(): void
    {
        $books = $this->data($this->createController([$this->book()->setSlug('La_Moto'), $this->book()])->index($this->request()))['books'];

        $this->assertNull($books[0]['page']);
        $this->assertSame('https://example.com/livre/la-moto-et-la-biche', $books[1]['page']);
    }

    // A book given no image names no cover, rather than the site's "no cover" picture
    public function testNamesTheCoverOrNothing(): void
    {
        $covered = $this->book()->addMedia(new BookMedia()->setKind('cover')->setName('medias/couverture.webp'));

        $books = $this->data($this->createController([$covered, $this->book()])->index($this->request()))['books'];

        $this->assertSame('https://example.com/medias/couverture.webp', $books[0]['cover']);
        $this->assertNull($books[1]['cover']);
    }

    // The timecodes name the pages by their number: a list with a hole would turn the wrong ones, and pages with no timecodes file cannot be followed
    public function testAPageOrTimecodesRowHoldingNoFileUnfollowsTheBook(): void
    {
        $missingPage = $this->book(pages: true, cues: true)->addMedia(new BookMedia()->setKind('page'));
        $missingCues = $this->book(pages: true)->addMedia(new BookMedia()->setKind('cues'));

        $books = $this->data($this->createController([$missingPage, $missingCues])->index($this->request()))['books'];

        foreach ($books as $book) {
            $this->assertSame([], $book['pages']);
            $this->assertNull($book['cues']);
        }
    }

    // A site serving its files from another host already names them in full, protocol-relative included
    public function testLeavesAnAlreadyAbsoluteUrlAlone(): void
    {
        $book = $this->data($this->createController([$this->book()], uriPrefix: '//cdn.example.com/')->index($this->request()))['books'][0];

        $this->assertSame('//cdn.example.com/medias/histoire.mp3', $book['audio']['url']);
    }

    // The page-turn sound is the site's choice, named only when the site turned it on
    public function testNamesThePageSoundOnlyWhenTheSiteTurnsItOn(): void
    {
        $this->assertNull($this->data($this->createController()->index($this->request()))['pageSound']);
        $this->assertSame(
            'https://example.com/bundles/c975lbook/sounds/page-turn.mp3',
            $this->data($this->createController(pageSound: true)->index($this->request()))['pageSound']
        );
    }

    // A reader already holding this very answer is told so, and sent nothing again
    public function testAnswersNotModifiedToTheSameAnswer(): void
    {
        $controller = $this->createController([$this->book()]);
        $etag = $controller->index($this->request())->getEtag();

        $request = $this->request();
        $request->headers->set('If-None-Match', (string) $etag);

        $this->assertSame(304, $controller->index($request)->getStatusCode());
    }

    // A language the site does not serve has no listening pages, as data either
    public function testALanguageTheSiteDoesNotServeIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController(translated: false)->index($this->request());
    }

    // What the book's own page says of it travels too: who made it, for whom, how many times it was heard, and its videos wherever they are held
    public function testGivesTheBooksInformationsVideosAndPlays(): void
    {
        $book = $this->book()
            ->setAge('3-8')
            ->setAuthor(new Contributor()->setName('Laurent Marquet'))
            ->addContributor(new BookContributor()->setRole('idea')->setContributor(new Contributor()->setName('Mattéo')))
            ->addVideo(new BookVideo()->setYoutubeUrl('https://www.youtube-nocookie.com/embed/abc'))
            ->addVideo(new BookVideo()->setName('medias/film.mp4'))
            ->addVideo(new BookVideo())
        ;

        $data = $this->data($this->createController([$book])->index($this->request()))['books'][0];

        $this->assertSame('3-8', $data['age']);
        $this->assertSame([['role' => 'label.author', 'name' => 'Laurent Marquet'], ['role' => 'Idée : idea', 'name' => 'Mattéo']], $data['credits']);
        $this->assertSame([
            ['title' => null, 'youtube' => 'https://www.youtube-nocookie.com/embed/abc', 'url' => null],
            ['title' => null, 'youtube' => null, 'url' => 'https://example.com/medias/film.mp4'],
        ], $data['videos']);
        $this->assertSame(3, $data['plays']);
    }

    // Below the site's minimum the count is withheld, as the book's own page withholds it
    public function testWithholdsThePlaysBelowTheMinimum(): void
    {
        $data = $this->data($this->createController([$this->book()], playsMinimum: '50')->index($this->request()))['books'][0];

        $this->assertNull($data['plays']);
    }

    // A serie is named as the book's own page names it, in the language the list is read in
    public function testTranslatesTheSeriesOfTheListedBooks(): void
    {
        $serie = new Serie()->setTitle('Les Histoires de Papa Câlin');
        $bookTranslator = $this->createMock(BookTranslator::class);
        $bookTranslator->expects($this->once())->method('apply')->with($this->containsIdentical($serie));

        $this->createController([$this->book()->setSerie($serie)], bookTranslator: $bookTranslator)->index($this->request());
    }

    // The roles are named in the language the listening page names them in: the url's when it reads one, the book's own otherwise
    public function testNamesTheRolesInTheListeningPageLanguage(): void
    {
        $book = $this->book()->setAuthor(new Contributor()->setName('Laurent Marquet'));

        $this->assertSame('label.author', $this->data($this->createController([$book])->index($this->request()))['books'][0]['credits'][0]['role']);
        $this->assertSame('en:label.author', $this->data($this->createController([$book], locale: 'en')->index($this->request()))['books'][0]['credits'][0]['role']);
    }

    // Only the fields of the site's own it lists travel, and nothing empty
    public function testGivesOnlyTheListedFieldsOfTheSite(): void
    {
        $book = $this->book()->setData(['idea' => 'Mattéo', 'age' => '', 'secret' => 'not for the app']);

        $this->assertSame(['idea' => 'Mattéo'], $this->data($this->createController([$book])->index($this->request()))['books'][0]['data']);
    }
}
