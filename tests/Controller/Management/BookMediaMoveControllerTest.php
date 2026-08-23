<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Controller\Management;

use c975L\BookBundle\Controller\Management\BookMediaMoveController;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Entity\Media;
use c975L\BookBundle\Service\BookMediaMoveRowAttrBuilder;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The only address of this bundle that writes on a POST: what it accepts of a drag-and-drop, and everything it turns down (see BookMediaMoveController)
class BookMediaMoveControllerTest extends TestCase
{
    private const int BOOK_ID = 7;

    // One row for the whole test, as Doctrine hands the very same instance to a file and to the book holding it - two objects carrying the same id are two different books to the controller, and rightly so
    private Book $book;

    protected function setUp(): void
    {
        $this->book = new Book();
        $this->setId($this->book, self::BOOK_ID);
    }

    // A file dragged onto the extracts becomes an extract of the book, whatever it was named before
    public function testAFileDraggedOntoTheExtractsBecomesOne(): void
    {
        $media = $this->media(kind: 'flipbook');

        $response = $this->move($media, BookMediaMoveRowAttrBuilder::TARGET_EXTRACT);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"moved":true}', $response->getContent());
        $this->assertSame(BookMediaMoveRowAttrBuilder::TARGET_EXTRACT, $media->getKind());
    }

    // The flipbook is what the page plays under the summary: a file landing there takes its name
    public function testAFileDraggedOntoTheFlipbookBecomesTheFlipbook(): void
    {
        $media = $this->media(kind: 'extract');

        $this->move($media, BookMediaMoveRowAttrBuilder::TARGET_FLIPBOOK);

        $this->assertSame(BookMediaMoveRowAttrBuilder::TARGET_FLIPBOOK, $media->getKind());
    }

    // The move is a POST that changes a row, so a token forged nowhere gets nothing done
    public function testAMoveWithoutAValidTokenIsRefused(): void
    {
        $media = $this->media(kind: 'flipbook');

        $response = $this->move($media, BookMediaMoveRowAttrBuilder::TARGET_EXTRACT, csrfValid: false);

        $this->assertSame(419, $response->getStatusCode());
        $this->assertSame('{"error":"invalid_csrf"}', $response->getContent());
        $this->assertSame('flipbook', $media->getKind());
    }

    // Composing a book is the editor's, whatever the browser posts
    public function testAVisitorWithoutTheEditorRoleIsTurnedAway(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->move($this->media(), BookMediaMoveRowAttrBuilder::TARGET_EXTRACT, granted: false);
    }

    // The id posted is whatever the page held: a row that is gone is no reason to fail on a null
    public function testAnUnknownFileAnswersNotFound(): void
    {
        $response = $this->move(null, BookMediaMoveRowAttrBuilder::TARGET_EXTRACT);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('{"error":"unknown_media"}', $response->getContent());
    }

    // The file stays in its own book: a collection of another book is no target, whatever the browser sent
    public function testAFileOfAnotherBookIsNotMoved(): void
    {
        $media = $this->media(kind: 'page');

        $response = $this->move($media, BookMediaMoveRowAttrBuilder::TARGET_EXTRACT, ownerId: self::BOOK_ID + 1);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('{"error":"media_not_owned"}', $response->getContent());
        $this->assertSame('page', $media->getKind());
    }

    // An edition is no target any more: it carries only what the book comes out under, its files belonging to the book (see BookEditionType)
    public function testAFormatIsNoTargetAnyMore(): void
    {
        $media = $this->media(kind: 'extract');

        $response = $this->move($media, 'edition:9');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('{"error":"unknown_target"}', $response->getContent());
        $this->assertSame('extract', $media->getKind());
    }

    // A target this version doesn't know is turned down rather than acted upon
    public function testATargetNamingNothingIsRefused(): void
    {
        $response = $this->move($this->media(kind: 'page'), 'whatever');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('{"error":"unknown_target"}', $response->getContent());
    }

    private function move(
        ?BookMedia $media,
        string $target,
        bool $csrfValid = true,
        bool $granted = true,
        ?int $ownerId = null,
    ): \Symfony\Component\HttpFoundation\JsonResponse {
        $request = new Request(request: [
            'id' => (string) ($media?->getId() ?? 999),
            'ownerId' => (string) ($ownerId ?? self::BOOK_ID),
            'target' => $target,
        ]);
        $request->headers->set('X-CSRF-Token', 'posted-token');
        $request->setSession($session = new Session(new MockArraySessionStorage()));

        $requestStack = new RequestStack([$request]);

        $controller = new BookMediaMoveController(
            $this->configService(),
            $this->entityManager($media),
            $this->translator(),
        );
        $controller->setContainer($this->container($requestStack, $csrfValid, $granted));

        $response = $controller->move($request);

        // The flash only stands where the move went through, the refusals answering json to a script that reloads nothing
        if (200 === $response->getStatusCode()) {
            $this->assertSame(['flash.media_moved'], $session->getFlashBag()->get('success'));
        }

        return $response;
    }

    private function media(string $kind = 'page'): BookMedia
    {
        $media = new BookMedia();
        $this->setId($media, 42);
        $media->setBook($this->book);
        $media->setKind($kind);

        return $media;
    }

    // Doctrine assigns the identifier itself, which a row built here never goes through - the column is held by Media for a file, by the row itself otherwise
    private function setId(object $entity, int $id): void
    {
        new \ReflectionProperty($entity instanceof Media ? Media::class : $entity::class, 'id')->setValue($entity, $id);
    }

    private function entityManager(?BookMedia $media): EntityManagerInterface
    {
        $mediaRepository = $this->createStub(EntityRepository::class);
        $mediaRepository->method('find')->willReturn($media);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($mediaRepository);

        return $entityManager;
    }

    private function configService(): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_EDITOR');

        return $configService;
    }

    private function translator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }

    private function container(RequestStack $requestStack, bool $csrfValid, bool $granted): ContainerInterface
    {
        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn($csrfValid);

        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn($granted);

        $services = [
            'request_stack' => $requestStack,
            'security.csrf.token_manager' => $csrfTokenManager,
            'security.authorization_checker' => $authorizationChecker,
        ];

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => isset($services[$id]));
        $container->method('get')->willReturnCallback(static fn (string $id) => $services[$id] ?? null);

        return $container;
    }
}
