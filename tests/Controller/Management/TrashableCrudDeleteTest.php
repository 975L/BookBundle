<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Controller\Management;

use c975L\BookBundle\Controller\Management\SerieCrudController;
use c975L\BookBundle\Controller\Management\StripCrudController;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Management\SerieExportProvider;
use c975L\BookBundle\Management\StripExportProvider;
use c975L\BookBundle\Service\BookCatalogExporter;
use c975L\BookBundle\Service\BookDuplicator;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\BookTranslator;
use c975L\BookBundle\Service\BookTrashManager;
use c975L\BookBundle\Tests\ServicesContainerTestTrait;
use c975L\ConfigBundle\Management\ContentLocaleScreen;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Service\BlockMoveRowAttrBuilder;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionConfigDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The batch "Delete" of EasyAdmin runs deleteEntity() on each checked row, from the catalog as from the trash (see Trait\TrashableCrudTrait::deleteEntity())
class TrashableCrudDeleteTest extends TestCase
{
    use ServicesContainerTestTrait;

    // Deleting a row of the catalog, one by one or in a batch, only moves it to the trash
    public function testDeletingALiveRowMovesItToTheTrash(): void
    {
        $strip = new Strip();
        $trashManager = $this->createMock(BookTrashManager::class);
        $trashManager->expects($this->once())->method('moveToTrash')->with($strip);
        $trashManager->expects($this->never())->method('deletePermanently');

        $this->stripController($trashManager)->deleteEntity($this->createStub(EntityManagerInterface::class), $strip);
    }

    // Deleting a row already in the trash, which is what the batch action of the trash does, removes it for good through the same path as its row button - the message for the whole selection is TrashBatchDeleteTrait's, said once rather than per row
    public function testDeletingATrashedRowRemovesItForGood(): void
    {
        $strip = new Strip()->setIsDeleted(true);
        $trashManager = $this->createMock(BookTrashManager::class);
        $trashManager->expects($this->once())->method('deletePermanently')->with($strip, 'strip_display');
        $trashManager->expects($this->never())->method('moveToTrash');

        $requestStack = $this->requestStack(true);
        $this->stripController($trashManager, requestStack: $requestStack)->deleteEntity($this->createStub(EntityManagerInterface::class), $strip);

        $this->assertSame([], $requestStack->getSession()->getFlashBag()->peek('success'));
    }

    // Removing for good is the admin's, EasyAdmin's single delete held to the bar of the row button - the batch is refused before its first row by TrashBatchDeleteTrait
    public function testAnEditorCannotRemoveATrashedRowForGood(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->stripController($this->untouchedTrashManager(), granted: false, trash: true)->deleteEntity($this->createStub(EntityManagerInterface::class), new Strip()->setIsDeleted(true));
    }

    // Only the batch "Delete" of the trash removes for good: a delete reaching a trashed row from anywhere else leaves it in the trash
    public function testATrashedRowIsOnlyRemovedFromTheTrash(): void
    {
        $this->stripController($this->untouchedTrashManager())->deleteEntity($this->createStub(EntityManagerInterface::class), new Strip()->setIsDeleted(true));
    }

    // A serie still holding a book is refused before the trait is reached, in the trash as on the catalog: the foreign key would refuse removing it anyway
    public function testASerieHoldingContentIsNeitherTrashedNorRemoved(): void
    {
        $serie = new Serie()->setIsDeleted(true)->addBook(new Book());

        $this->serieController($this->untouchedTrashManager())->deleteEntity($this->createStub(EntityManagerInterface::class), $serie);
    }

    // An empty serie in the trash reaches the trait and is removed for good
    public function testAnEmptyTrashedSerieIsRemovedForGood(): void
    {
        $serie = new Serie()->setIsDeleted(true);
        $trashManager = $this->createMock(BookTrashManager::class);
        $trashManager->expects($this->once())->method('deletePermanently')->with($serie);

        $this->serieController($trashManager)->deleteEntity($this->createStub(EntityManagerInterface::class), $serie);
    }

    // The trash is titled as such, the catalog by its plural
    public function testTheTrashIndexIsTitledTrash(): void
    {
        $trash = $this->stripController($this->createStub(BookTrashManager::class), trash: true)->configureCrud(Crud::new())->getAsDto();
        $catalog = $this->stripController($this->createStub(BookTrashManager::class))->configureCrud(Crud::new())->getAsDto();

        $this->assertSame('action.trash', $trash->getCustomPageTitle(Crud::PAGE_INDEX)?->getMessage());
        $this->assertSame('label.strips', $catalog->getCustomPageTitle(Crud::PAGE_INDEX)?->getMessage());
    }

    // The batch "Delete" says what it does on each screen, EasyAdmin's own "Delete" saying neither (see EasyAdminActionHelper::trashBatchDelete())
    public function testTheBatchDeleteIsWordedForTheCatalogAndForTheTrash(): void
    {
        $labels = array_map(fn (bool $trash): ?string => $this->stripActions($trash)->getAction(Crud::PAGE_INDEX, Action::BATCH_DELETE)?->getLabel()?->getMessage(), [false, true]);

        $this->assertSame(['action.move_to_trash', 'action.delete_permanently'], $labels);
    }

    // Emptying the trash in a batch is the admin's, like its row button, while the catalog's batch stays the editor's - an editor is not shown a button TrashBatchDeleteTrait would refuse
    public function testOnlyTheAdminIsOfferedToEmptyTheTrashInABatch(): void
    {
        $permissions = array_map(fn (bool $trash): ?string => $this->stripActions($trash)->getActionPermissions()[Action::BATCH_DELETE] ?? null, [false, true]);

        $this->assertSame(['ROLE_EDITOR', 'ROLE_ADMIN'], $permissions);
    }

    // EasyAdmin drops the "trash" flag from a batch url: the batch "Delete" of the trash carries its own, the catalog's keeping EasyAdmin's crud action
    public function testTheBatchDeleteOfTheTrashPostsBackToTheTrash(): void
    {
        $this->assertNull($this->stripActions(false)->getAction(Crud::PAGE_INDEX, Action::BATCH_DELETE)?->getUrl());
        $this->assertIsCallable($this->stripActions(true)->getAction(Crud::PAGE_INDEX, Action::BATCH_DELETE)?->getUrl());
    }

    // A trash manager the row must not reach, neither to trash it nor to remove it
    private function untouchedTrashManager(): BookTrashManager
    {
        $trashManager = $this->createMock(BookTrashManager::class);
        $trashManager->expects($this->never())->method('deletePermanently');
        $trashManager->expects($this->never())->method('moveToTrash');

        return $trashManager;
    }

    // The actions of the strip index, its batch "Delete" included, on the catalog or in the trash
    private function stripActions(bool $trash): ActionConfigDto
    {
        $actions = Actions::new()
            ->add(Crud::PAGE_INDEX, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DELETE)
            ->addBatchAction(Action::BATCH_DELETE);

        return $this->stripController($this->createStub(BookTrashManager::class), trash: $trash)->configureActions($actions)->getAsDto(null);
    }

    private function stripController(BookTrashManager $trashManager, bool $granted = true, bool $trash = false, ?RequestStack $requestStack = null): StripCrudController
    {
        return $this->controller(StripCrudController::class, StripExportProvider::class, $trashManager, $granted, $requestStack ?? $this->requestStack($trash));
    }

    private function serieController(BookTrashManager $trashManager): SerieCrudController
    {
        return $this->controller(SerieCrudController::class, SerieExportProvider::class, $trashManager, true, $this->requestStack(true));
    }

    // Both screens take the same collaborators, only their export provider differing
    /**
     * @template T of StripCrudController|SerieCrudController
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function controller(string $class, string $exportProvider, BookTrashManager $trashManager, bool $granted, RequestStack $requestStack): object
    {
        $controller = new $class(
            $this->createStub(AdminContextProviderInterface::class),
            new \ReflectionClass(AdminUrlGenerator::class)->newInstanceWithoutConstructor(),
            $this->createStub(BlockMoveRowAttrBuilder::class),
            $this->createStub(BookCatalogExporter::class),
            $this->createStub(BookDuplicator::class),
            $this->createStub(BookPublicUrlResolver::class),
            $trashManager,
            $this->configService(),
            $this->createStub(CsrfTokenManagerInterface::class),
            $requestStack,
            $this->createStub($exportProvider),
            $this->createStub(TranslatorInterface::class),
        );
        $controller->setContainer($this->container($granted, $requestStack));

        return $controller;
    }

    // A request carrying a session, which the refusal flash of the serie needs, and the "trash" flag of the index when asked
    private function requestStack(bool $trash): RequestStack
    {
        $request = new Request($trash ? ['trash' => 1] : []);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return new RequestStack([$request]);
    }

    private function configService(): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $key): string => 'site-role-admin' === $key ? 'ROLE_ADMIN' : 'ROLE_EDITOR');

        return $configService;
    }

    private function container(bool $granted, RequestStack $requestStack): ContainerInterface
    {
        $contentLocaleScreen = $this->createStub(ContentLocaleScreen::class);
        $contentLocaleScreen->method('action')->willReturn(Action::new('translate', 'Translate', 'fa fa-language')->linkToUrl('/translate'));

        return $this->securityContainer(true, $granted, [
            ContentLocaleScreen::class => $contentLocaleScreen,
            BookTranslator::class => $this->createStub(BookTranslator::class),
            TranslatorInterface::class => $this->createStub(TranslatorInterface::class),
            'request_stack' => $requestStack,
        ]);
    }
}
