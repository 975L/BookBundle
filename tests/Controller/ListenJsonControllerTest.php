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
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Entity\Media;
use c975L\BookBundle\Service\BookServiceInterface;
use c975L\BookBundle\Service\BookTranslatedLocales;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\LocalizedRouteNegotiator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Vich\UploaderBundle\Templating\Helper\UploaderHelperInterface;

// The listening pages as data hand out what the listening page plays, under absolute urls, and nothing a book does not carry
class ListenJsonControllerTest extends TestCase
{
    /**
     * @param Book[] $published
     */
    private function createController(array $published = [], bool $translated = true, bool $pageSound = false, string $uriPrefix = '/'): ListenJsonController
    {
        $bookService = $this->createStub(BookServiceInterface::class);
        $bookService->method('findAllPublished')->willReturn($published);

        $negotiator = $this->createStub(LocalizedRouteNegotiator::class);
        $negotiator->method('isTranslated')->willReturn($translated);
        $negotiator->method('vary')->willReturnCallback(static fn (Request $request, Response $response): Response => $response);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($pageSound);
        $configService->method('getBool')->willReturnCallback(static fn (mixed $value): bool => (bool) $value);

        $packages = $this->createStub(Packages::class);
        $packages->method('getUrl')->willReturnCallback(static fn (string $path): string => '/' . $path);

        // A real one, the class being final: it reads the host off the request being answered
        $requestStack = new RequestStack([$this->request()]);

        // What the mapping answers: its uri_prefix before the file's name, and nothing for a row holding no file
        $uploaderHelper = $this->createStub(UploaderHelperInterface::class);
        $uploaderHelper->method('asset')->willReturnCallback(static fn (Media $media): ?string => null === $media->getName() ? null : $uriPrefix . $media->getName());

        $controller = new ListenJsonController($bookService, $this->createStub(BookTranslatedLocales::class), $negotiator, $configService, $packages, new UrlHelper($requestStack), $uploaderHelper);
        $controller->setContainer(new Container());

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

    private function request(): Request
    {
        return Request::create('https://example.com/ecouter.json');
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
}
