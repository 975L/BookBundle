<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Management;

use c975L\BookBundle\Controller\Management\BookCrudController;
use c975L\BookBundle\Controller\Management\SerieCrudController;
use c975L\BookBundle\Controller\Management\StripCrudController;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Management\BookBlockEditUrlProvider;
use c975L\BookBundle\Repository\BookCategoryRepository;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Repository\ContributorRepository;
use c975L\BookBundle\Repository\SerieRepository;
use c975L\BookBundle\Repository\StripRepository;
use c975L\UiBundle\Entity\Block;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;

// The blocks composed at the foot of a display page are the one part of it UiBundle draws itself: without this provider they are the only zone of the page hovering no pencil at all
class BookBlockEditUrlProviderTest extends TestCase
{
    // A block owned by a known book resolves to that book's edit screen, opened on the block's own row
    public function testABlockOwnedByABookResolvesToTheBooksEditScreen(): void
    {
        $block = $this->blockWithId(10);

        $book = new Book();
        $book->addBlock($block);
        $this->withId($book, 4);

        $urls = $this->provider(books: [$book])->getEditUrls([$block]);

        $this->assertSame([10 => 'BookCrudController/4/10'], $urls);
    }

    // Each family is asked, so a serie's and a planche's blocks are answered by the same provider
    public function testASeriesAndAPlanchesBlocksAreAnsweredToo(): void
    {
        $serieBlock = $this->blockWithId(11);
        $serie = new Serie();
        $serie->addBlock($serieBlock);
        $this->withId($serie, 5);

        $stripBlock = $this->blockWithId(12);
        $strip = new Strip();
        $strip->addBlock($stripBlock);
        $this->withId($strip, 6);

        $urls = $this->provider(series: [$serie], strips: [$strip])->getEditUrls([$serieBlock, $stripBlock]);

        $this->assertSame([
            11 => 'SerieCrudController/5/11',
            12 => 'StripCrudController/6/12',
        ], $urls);
    }

    // The three classes the provider hands BlockFocusUrl - ManagementTargetsTest cannot reach them, its test case walking a provider only by the five management interfaces this one implements none of, and EasyAdmin throws on the public page rather than in the back office when handed a class that is no longer a CRUD controller
    public function testEveryFamilyNamesACrudController(): void
    {
        foreach ([BookCrudController::class, SerieCrudController::class, StripCrudController::class] as $controller) {
            $this->assertTrue(
                is_subclass_of($controller, AbstractCrudController::class),
                sprintf('"%s" is no longer a CRUD controller, and EasyAdmin cannot generate an edit url for it', $controller)
            );
        }
    }

    // A block this bundle owns nothing of is left to whichever provider does own it
    public function testABlockNoFamilyOwnsIsLeftAlone(): void
    {
        $this->assertSame([], $this->provider()->getEditUrls([$this->blockWithId(10)]));
    }

    // Nothing to look up: a page rendering only blocks it never saved would otherwise query the four families for an empty list
    public function testABlockWithNoIdQueriesNothing(): void
    {
        $repository = $this->createMock(BookRepository::class);
        $repository->expects($this->never())->method('findByBlockIds');

        $provider = new BookBlockEditUrlProvider(
            $this->adminUrlGenerator(),
            $this->createStub(BookCategoryRepository::class),
            $repository,
            $this->createStub(ContributorRepository::class),
            $this->createStub(SerieRepository::class),
            $this->createStub(StripRepository::class),
        );

        $this->assertSame([], $provider->getEditUrls([new Block()]));
    }

    private function provider(array $books = [], array $series = [], array $strips = [], array $contributors = [], array $categories = []): BookBlockEditUrlProvider
    {
        return new BookBlockEditUrlProvider(
            $this->adminUrlGenerator(),
            $this->repository(BookCategoryRepository::class, $categories),
            $this->repository(BookRepository::class, $books),
            $this->repository(ContributorRepository::class, $contributors),
            $this->repository(SerieRepository::class, $series),
            $this->repository(StripRepository::class, $strips),
        );
    }

    private function repository(string $class, array $owners): object
    {
        $repository = $this->createStub($class);
        $repository->method('findByBlockIds')->willReturn($owners);

        return $repository;
    }

    // Rebuilds the three names the URL is made of - the screen, the entity and the block it opens on - so what each block points at is what is asserted
    private function adminUrlGenerator(): AdminUrlGeneratorInterface
    {
        $controller = null;
        $entityId = null;
        $focusBlock = null;

        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setAction')->willReturnSelf();
        $generator->method('setController')->willReturnCallback(function (string $fqcn) use (&$controller, $generator) {
            $controller = substr((string) strrchr($fqcn, '\\'), 1);

            return $generator;
        });
        $generator->method('setEntityId')->willReturnCallback(function ($id) use (&$entityId, $generator) {
            $entityId = $id;

            return $generator;
        });
        $generator->method('set')->willReturnCallback(function (string $name, $value) use (&$focusBlock, $generator) {
            $focusBlock = 'focusBlock' === $name ? $value : $focusBlock;

            return $generator;
        });
        $generator->method('generateUrl')->willReturnCallback(static function () use (&$controller, &$entityId, &$focusBlock): string {
            return sprintf('%s/%s/%s', $controller, $entityId, $focusBlock);
        });

        return $generator;
    }

    private function blockWithId(int $id): Block
    {
        $block = new Block();
        new \ReflectionProperty(Block::class, 'id')->setValue($block, $id);

        return $block;
    }

    private function withId(object $entity, int $id): void
    {
        new \ReflectionProperty($entity::class, 'id')->setValue($entity, $id);
    }
}
