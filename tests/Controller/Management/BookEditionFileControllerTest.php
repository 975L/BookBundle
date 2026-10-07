<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Controller\Management;

use c975L\BookBundle\Controller\Management\BookEditionFileController;
use c975L\BookBundle\Entity\BookEditionFile;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Service\PrivateFileResponseFactoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

// The admin address opening a file an edition holds, from outside public/ (see BookEditionFileController)
class BookEditionFileControllerTest extends TestCase
{
    // The file is read from the private directory, under the name it was stored with, and handed out under its own
    public function testTheFileIsServedFromThePrivateDirectory(): void
    {
        $response = $this->createStub(BinaryFileResponse::class);
        $factory = $this->createMock(PrivateFileResponseFactoryInterface::class);
        $factory->expects($this->once())
            ->method('createInlineResponse')
            ->with('/project/private/medias/editions/book-epub/story.epub', 'story.epub')
            ->willReturn($response);

        $this->assertSame($response, $this->open($this->file('medias/editions/book-epub/story.epub'), $factory));
    }

    // Opening a sold file is the editor's, never a visitor guessing ids
    public function testAVisitorWithoutTheEditorRoleIsTurnedAway(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->open($this->file('story.epub'), granted: false);
    }

    // A row that is gone, or one holding no file, answers 404
    public function testAnUnknownOrEmptyRowAnswersNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->open(new BookEditionFile());
    }

    // A file missing on disk answers 404, not the 500 a missing path would throw
    public function testAFileMissingOnDiskAnswersNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->open($this->file('gone.pdf'));
    }

    private function open(?BookEditionFile $file, ?PrivateFileResponseFactoryInterface $factory = null, bool $granted = true): mixed
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('find')->willReturn($file);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_EDITOR');

        $controller = new BookEditionFileController(
            $configService,
            $entityManager,
            $factory ?? $this->createStub(PrivateFileResponseFactoryInterface::class),
            '/project',
        );
        $controller->setContainer($this->container($granted));

        return $controller->open(new Request(['id' => '7']));
    }

    private function file(string $name): BookEditionFile
    {
        return new BookEditionFile()->setName($name);
    }

    private function container(bool $granted): ContainerInterface
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn($granted);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => 'security.authorization_checker' === $id);
        $container->method('get')->willReturn($authorizationChecker);

        return $container;
    }
}
