<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Controller;

use c975L\BookBundle\Controller\ListenController;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Service\BookServiceInterface;
use c975L\BookBundle\Service\BookTranslatedLocales;
use c975L\BookBundle\Service\BookTranslator;
use c975L\ConfigBundle\Service\LocalizedRouteNegotiator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

// The listening pages list the books holding a recording, and play one only when there is something to play
class ListenControllerTest extends TestCase
{
    /** @var array<string, mixed> what the last render was handed */
    private array $rendered = [];

    private function createController(?Book $book = null, array $published = []): ListenController
    {
        $bookService = $this->createStub(BookServiceInterface::class);
        $bookService->method('findOneBySlug')->willReturn($book);
        $bookService->method('findAllPublished')->willReturn($published);

        $controller = new ListenController($bookService, $this->createStub(BookTranslatedLocales::class), $this->createStub(BookTranslator::class), $this->createNegotiator());

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(function (string $template, array $context): string {
            $this->rendered = $context;

            return '<page>';
        });

        $container = new Container();
        $container->set('twig', $twig);
        $controller->setContainer($container);

        return $controller;
    }

    private function book(bool $audio = true, bool $hidden = false, bool $deleted = false): Book
    {
        $book = new Book()
            ->setTitle('La Moto et la Biche')
            ->setSlug('la-moto-et-la-biche')
            ->setHidden($hidden)
            ->setIsDeleted($deleted)
        ;
        if ($audio) {
            $book->addMedia(new BookMedia()->setKind('audio_mp3')->setName('histoire.mp3'));
        }

        return $book;
    }

    // A book with no recording has nothing to offer here: the index leaves it out
    public function testTheIndexListsOnlyTheBooksHoldingARecording(): void
    {
        $withAudio = $this->book();
        $response = $this->createController(published: [$withAudio, $this->book(audio: false)])->index(new Request());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([$withAudio], $this->rendered['books']);
    }

    public function testABookHoldingARecordingIsPlayed(): void
    {
        $this->assertSame(200, $this->createController($this->book())->display('la-moto-et-la-biche', new Request())->getStatusCode());
    }

    public function testABookWithoutRecordingIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController($this->book(audio: false))->display('la-moto-et-la-biche', new Request());
    }

    // Filed as audio is not enough: a file the player cannot read would leave the page with nothing to play
    public function testARecordingThePlayerCannotReadCountsAsNone(): void
    {
        $book = $this->book(audio: false);
        $book->addMedia(new BookMedia()->setKind('audio_mp3')->setName('histoire.flac'));

        $this->createController(published: [$book])->index(new Request());
        $this->assertSame([], $this->rendered['books']);

        $this->expectException(NotFoundHttpException::class);
        $this->createController($book)->display('la-moto-et-la-biche', new Request());
    }

    public function testASlugNoBookCarriesIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController()->display('inconnu', new Request());
    }

    // Same answers as the book's own page: gone for the trash, missing for one set aside
    public function testABookInTheTrashIsGone(): void
    {
        $this->expectException(GoneHttpException::class);

        $this->createController($this->book(deleted: true))->display('la-moto-et-la-biche', new Request());
    }

    public function testABookSetAsideIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController($this->book(hidden: true))->display('la-moto-et-la-biche', new Request());
    }

    // The negotiator as a screen answering in one language alone meets it: nothing to refuse, nobody to move, and the response handed straight back
    private function createNegotiator(): LocalizedRouteNegotiator
    {
        $negotiator = $this->createStub(LocalizedRouteNegotiator::class);
        $negotiator->method('isTranslated')->willReturn(true);
        $negotiator->method('redirectToAskedLanguage')->willReturn(null);
        $negotiator->method('vary')->willReturnCallback(static fn (Request $request, Response $response): Response => $response);

        return $negotiator;
    }
}
