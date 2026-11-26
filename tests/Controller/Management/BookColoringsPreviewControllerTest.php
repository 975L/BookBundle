<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Controller\Management;

use c975L\BookBundle\Contract\BookCustomizationProviderInterface;
use c975L\BookBundle\Controller\Management\BookColoringsPreviewController;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookColoring;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use c975L\BookBundle\Tests\ServicesContainerTestTrait;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

// The drawings of a book shown before their day, grouped by the site's levels, to whoever edits them and to no search engine
class BookColoringsPreviewControllerTest extends TestCase
{
    use ServicesContainerTestTrait;

    // The site's levels first in its order, whatever the rank of the drawings, then those of no level or of one it no longer names
    public function testTheDrawingsAreGroupedByLevel(): void
    {
        $book = new Book();
        foreach ([[1, 'item-3'], [2, 'big'], [3, null], [4, 'small'], [5, 'big']] as [$position, $kind]) {
            $book->addColoring(new BookColoring()->setPosition($position)->setKind($kind)->setPublishedOn(new \DateTimeImmutable('+1 year')));
        }

        $groups = $this->controller(null)->groups($book);

        $this->assertSame(['small', 'big', null], array_column($groups, 'kind'));
        $this->assertSame([2, 5], array_map(static fn (BookColoring $coloring): ?int => $coloring->getPosition(), $groups[1]['colorings']));
        $this->assertCount(2, $groups[2]['colorings']);
    }

    // A book holding no drawing has no group at all, the page saying so
    public function testABookWithoutDrawingsHasNoGroup(): void
    {
        $this->assertSame([], $this->controller(null)->groups(new Book()));
    }

    public function testThePreviewIsKeptOutOfSearchEngines(): void
    {
        $response = $this->controller(new Book())->preview(1);

        $this->assertSame('noindex', $response->headers->get('X-Robots-Tag'));
        $this->assertSame('preview', $response->getContent());
    }

    public function testAnUnknownBookAnswersNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller(null)->preview(1);
    }

    public function testAVisitorWithoutTheEditorRoleIsTurnedAway(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->controller(new Book(), false)->preview(1);
    }

    // The controller over the given book, on a site naming the levels "small" and "big"
    private function controller(?Book $book, bool $granted = true): BookColoringsPreviewController
    {
        $provider = $this->createStub(BookCustomizationProviderInterface::class);
        $provider->method('getPrintableKinds')->willReturn(['small' => 'item-1', 'big' => 'item-2']);

        $repository = $this->createStub(EntityRepository::class);
        $repository->method('find')->willReturn($book);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_EDITOR');

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('preview');

        $controller = new BookColoringsPreviewController($configService, new BookCustomizationRegistry([$provider], $this->createStub(TranslatorInterface::class)), $entityManager);
        $controller->setContainer($this->securityContainer(true, $granted, ['twig' => $twig]));

        return $controller;
    }
}
