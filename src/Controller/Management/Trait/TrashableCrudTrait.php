<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Controller\Management\Trait;

use c975L\BookBundle\Contract\TrashableInterface;
use c975L\ConfigBundle\Management\EasyAdminActionHelper;
use c975L\ConfigBundle\Service\Export\ExportFormat;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\ActionGroup;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use function Symfony\Component\Translation\t;

// What the three screens of the catalog do alike with a row: its actions, its trash, its copy and its exports. A serie, a book and a strip only differ here by the words they are named with and the table and route they answer on, all of them constants of the controller using this trait - the screens themselves stay whole, each keeping its own fields, its own templates and whatever it does on its own (see SerieCrudController::deleteEntity(), which refuses the trash to a serie still holding a book or a strip)
trait TrashableCrudTrait
{
    public function persistEntity(EntityManagerInterface $entityManager, mixed $entity): void
    {
        $entity->setCreation(new \DateTime());
        $entity->setModification(new \DateTime());
        $entity->setUser($this->getUser());

        parent::persistEntity($entityManager, $entity);
    }

    public function updateEntity(EntityManagerInterface $entityManager, mixed $entity): void
    {
        // A renamed row keeps its old url answering, with a 301 to the new one - a visitor's bookmark and a search engine's index both hold it (see BookTrashManager::redirectSlugChange())
        $originalSlug = $entityManager->getUnitOfWork()->getOriginalEntityData($entity)['slug'] ?? null;
        if (null !== $originalSlug && $originalSlug !== $entity->getSlug()) {
            $this->trashManager->redirectSlugChange($this->displayRoute($entity), (string) $originalSlug, (string) $entity->getSlug());
        }

        $entity->setModification(new \DateTime());
        $entity->setUser($this->getUser());

        parent::updateEntity($entityManager, $entity);
    }

    // A declaration of actions, one block per action: its length says how many buttons the screen wears, not how much the method decides
    /** @SuppressWarnings(PHPMD.ExcessiveMethodLength) */
    public function configureActions(Actions $actions): Actions
    {
        $role = $this->configService->get('site-role-editor');
        // A raw dump of the whole table is not what composing a catalog needs, so the three exports stay stricter than everything else here - the methods themselves check that same role again
        $exportRole = $this->configService->get('site-role-admin');

        $exportGroup = ActionGroup::new('export', t('label.export', [], 'book'), 'fa fa-download')
            ->createAsGlobalActionGroup()
            ->addAction(Action::new('exportSql', t('label.export_sql', [], 'book'))->linkToCrudAction('exportSql'))
            ->addAction(Action::new('exportCsv', t('label.export_csv', [], 'book'))->linkToCrudAction('exportCsv'))
            ->addAction(Action::new('exportJson', t('label.export_json', [], 'book'))->linkToCrudAction('exportJson'))
        ;

        // The checked rows as a re-importable zip, same "export selection" as GalleryBundle's GalleryCategoryCrudController and SiteBundle's PageCrudController - a batch action rather than one of the group above, which dumps the whole table and nothing of what hangs off it
        $exportSelectionAction = Action::new('exportSelection', t('label.export_selection', [], 'book'), 'fa fa-file-export')
            ->createAsBatchAction()
            ->linkToCrudAction('exportSelection');

        // Opens the public page of the row on the site, in a new tab - hidden when this site doesn't serve that family (empty route prefix) or when the row has no slug yet
        $viewOnSiteAction = Action::new('viewOnSite', t('action.view_on_site', [], 'book'), 'fa fa-external-link-alt')
            ->linkToUrl(fn (TrashableInterface $entity): string => (string) $this->publicPath($entity))
            ->setHtmlAttributes(['target' => '_blank'])
            ->displayIf(fn (TrashableInterface $entity): bool => !$entity->isDeleted() && null !== $this->publicPath($entity))
            ->addCssClass('btn btn-secondary');

        // Duplicates the row with everything that belongs to it into a new one, saved immediately (see duplicate() and BookDuplicator) - built as a url rather than linked to the crud action, so the csrf token the action checks travels with it
        $duplicateAction = Action::new('duplicate', t('action.duplicate', [], 'book'), 'fa fa-copy')
            ->linkToUrl(fn (TrashableInterface $entity): string => $this->actionUrl('duplicate', $entity, self::DUPLICATE_CSRF_TOKEN))
            ->displayIf(static fn (TrashableInterface $entity): bool => !$entity->isDeleted())
            ->askConfirmation(t('confirm.duplicate', [], 'book'))
            ->addCssClass('btn btn-secondary');

        // Restores a row out of the trash, only shown once already in it
        $restoreAction = Action::new('restore', t('action.restore', [], 'book'), 'fa fa-trash-restore')
            ->linkToUrl(fn (TrashableInterface $entity): string => $this->trashActionUrl('restore', $entity, self::RESTORE_CSRF_TOKEN))
            ->displayIf(static fn (TrashableInterface $entity): bool => $entity->isDeleted())
            ->addCssClass('btn btn-secondary');

        // Removes the row for good, only shown once already in the trash - built as a url rather than linked to the crud action, so the csrf token the action checks travels with it
        $deletePermanentlyAction = Action::new('deletePermanently', t('action.delete_permanently', [], 'book'), 'fa fa-trash')
            ->linkToUrl(fn (TrashableInterface $entity): string => $this->trashActionUrl('deletePermanently', $entity, self::DELETE_PERMANENTLY_CSRF_TOKEN))
            ->displayIf(static fn (TrashableInterface $entity): bool => $entity->isDeleted())
            ->askConfirmation(t('confirm.delete_permanently', [], 'book'))
            ->asDangerAction()
            ->addCssClass('btn btn-danger');

        // Lets the admin back out of a create/edit without saving - mirrors EasyAdmin's own built-in actions (linkToCrudAction targeting INDEX, same as Action::INDEX itself)
        $cancelAction = Action::new('cancel', $this->translator->trans('action.cancel', [], 'EasyAdminBundle'), 'fa fa-times')
            ->linkToCrudAction(Action::INDEX)
            ->addCssClass('btn btn-secondary');

        return $actions
            ->add(Crud::PAGE_INDEX, $exportGroup)
            ->add(Crud::PAGE_INDEX, $exportSelectionAction)
            ->add(Crud::PAGE_INDEX, $this->trashAction())
            ->add(Crud::PAGE_INDEX, $restoreAction)
            ->add(Crud::PAGE_INDEX, $deletePermanentlyAction)
            ->add(Crud::PAGE_NEW, $cancelAction)
            ->add(Crud::PAGE_EDIT, $cancelAction)
            ->add(Crud::PAGE_INDEX, $viewOnSiteAction)
            ->add(Crud::PAGE_EDIT, $viewOnSiteAction)
            ->add(Crud::PAGE_INDEX, $duplicateAction)
            ->add(Crud::PAGE_EDIT, $duplicateAction)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action->displayIf(static fn (TrashableInterface $entity): bool => !$entity->isDeleted()),
                $this->translator->trans('action.edit', [], 'EasyAdminBundle'),
            ))
            ->update(Crud::PAGE_INDEX, 'viewOnSite', fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.view_on_site', [], 'book'),
            ))
            ->update(Crud::PAGE_INDEX, 'duplicate', fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.duplicate', [], 'book'),
            ))
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action
                    ->setIcon('fa fa-box-archive')
                    ->askConfirmation(t('confirm.move_to_trash', [], 'book'))
                    ->displayIf(static fn (TrashableInterface $entity): bool => !$entity->isDeleted()),
                $this->translator->trans('action.move_to_trash', [], 'book'),
            ))
            ->update(Crud::PAGE_INDEX, 'restore', fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.restore', [], 'book'),
            ))
            ->update(Crud::PAGE_INDEX, 'deletePermanently', fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.delete_permanently', [], 'book'),
            ))
            ->reorder(Crud::PAGE_INDEX, array_values(array_filter([Action::EDIT, 'viewOnSite', 'duplicate', ...$this->extraIndexActions(), Action::DELETE, 'restore', 'deletePermanently'])))
            ->setPermission(Action::INDEX, $role)
            ->setPermission(Action::NEW, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            ->setPermission('viewOnSite', $role)
            ->setPermission('duplicate', $role)
            ->setPermission('trash', $role)
            ->setPermission('restore', $exportRole)
            ->setPermission('deletePermanently', $exportRole)
            ->setPermission('exportSql', $exportRole)
            ->setPermission('exportCsv', $exportRole)
            ->setPermission('exportJson', $exportRole)
            ->setPermission('exportSelection', $exportRole)
            // Detail adds no information beyond what edit already shows
            ->disable(Action::DETAIL)
        ;
    }

    // The actions a screen adds between the copy and the trash: they must be named here to keep their place in the row, reorder() listing only what it knows. Empty for a screen adding none
    /** @return list<string> */
    protected function extraIndexActions(): array
    {
        return [];
    }

    // Duplicates the row with everything it holds, saved immediately, then opens the copy for editing (see the duplicateEntity() of each screen and BookDuplicator)
    #[AdminRoute('/{entityId}/duplicate')]
    public function duplicate(AdminContext $context, EntityManagerInterface $entityManager, Request $request): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        if (!$this->isCsrfTokenValid(self::DUPLICATE_CSRF_TOKEN, $request->query->getString('token'))) {
            return $this->redirect($this->indexUrl());
        }

        $copy = $this->duplicateEntity($context->getEntity()->getInstance());

        $entityManager->persist($copy);
        $entityManager->flush();

        $this->addFlash('success', $this->translator->trans(self::FLASH_DUPLICATED, [], 'book'));

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::EDIT)
                ->setEntityId($copy->getId())
                ->generateUrl()
        );
    }

    // Only the rows on the site, or only the ones in the trash when that is the view being asked for
    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters,
    ): QueryBuilder {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->andWhere('entity.isDeleted = :isDeleted')
            ->setParameter('isDeleted', $this->isTrashView())
        ;
    }

    // Deleting only takes the row off the site: it goes to the trash, where it can be brought back or removed for good (see BookTrashManager)
    public function deleteEntity(EntityManagerInterface $entityManager, mixed $entity): void
    {
        $this->trashManager->moveToTrash($entity);
    }

    // Restores the row out of the trash - back on the site exactly as it left it
    #[AdminRoute('/{entityId}/restore')]
    public function restore(AdminContext $context, Request $request): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        if (!$this->isCsrfTokenValid(self::RESTORE_CSRF_TOKEN, $request->query->getString('token'))) {
            return $this->redirect($this->trashIndexUrl());
        }

        $this->trashManager->restore($context->getEntity()->getInstance());

        $this->addFlash('success', $this->translator->trans(self::FLASH_RESTORED, [], 'book'));

        return $this->redirect($this->trashIndexUrl());
    }

    // Removes the row for good, its url left answering 410 rather than dropping back to a 404 (see BookTrashManager::deletePermanently()) - only reachable once already in the trash
    #[AdminRoute('/{entityId}/delete-permanently')]
    public function deletePermanently(AdminContext $context, Request $request): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        if (!$this->isCsrfTokenValid(self::DELETE_PERMANENTLY_CSRF_TOKEN, $request->query->getString('token'))) {
            return $this->redirect($this->trashIndexUrl());
        }

        $entity = $context->getEntity()->getInstance();
        $this->trashManager->deletePermanently($entity, $this->displayRoute($entity));

        $this->addFlash('success', $this->translator->trans(self::FLASH_DELETED_PERMANENTLY, [], 'book'));

        return $this->redirect($this->trashIndexUrl());
    }

    #[AdminRoute]
    public function exportSql(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        return $this->catalogExporter->exportTable(ExportFormat::Sql, self::EXPORT_TABLE);
    }

    #[AdminRoute]
    public function exportCsv(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        return $this->catalogExporter->exportTable(ExportFormat::Csv, self::EXPORT_TABLE);
    }

    #[AdminRoute]
    public function exportJson(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        return $this->catalogExporter->exportTable(ExportFormat::Json, self::EXPORT_TABLE);
    }

    // Exports the checked rows - with their versions, their platforms, their blocks and their files bundled in the archive - as a zip meant to be re-uploaded on another site through ConfigBundle's "Import content" screen (see eg. Management\BookImportProvider). Stricter than the row actions like the three dumps above, and checked again here
    #[AdminRoute]
    public function exportSelection(AdminContext $context, BatchActionDto $batchActionDto): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        if (static::getEntityFqcn() !== $batchActionDto->getEntityFqcn()) {
            throw new BadRequestHttpException();
        }

        if (!$this->isCsrfTokenValid('ea-batch-action-exportSelection-' . $batchActionDto->getEntityFqcn(), $batchActionDto->getCsrfToken())) {
            return $this->redirect($this->indexUrl());
        }

        $data = $this->serializeSelection($batchActionDto->getEntityIds());

        return $this->catalogExporter->exportSelection(self::EXPORT_KIND, $data['items'], $data['files']);
    }

    // The copy the duplicate action saves, made by the very method of BookDuplicator that knows what this row holds
    abstract protected function duplicateEntity(mixed $entity): object;

    // The checked rows, serialized by the export provider of this very family - the same one the "export sync all" dashboard shortcut reads (see eg. Management\BookExportProvider)
    // @param list<int> $ids
    // @return array{items: list<array<string, mixed>>, files: array<string, string>}
    abstract protected function serializeSelection(array $ids): array;

    // Toggles between the catalog and its trash - the same index, listing what left the site instead of what is on it
    private function trashAction(): Action
    {
        $action = $this->isTrashView()
            ? Action::new('trash', t(self::TRASH_BACK_LABEL, [], 'book'), self::TRASH_BACK_ICON)
                ->linkToUrl(fn (): string => $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::INDEX)
                    ->unset('trash')
                    ->generateUrl())
            : Action::new('trash', t('action.trash', [], 'book'), 'fa fa-trash-alt')
                ->linkToUrl(fn (): string => $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::INDEX)
                    ->set('trash', 1)
                    ->generateUrl());

        return $action
            ->createAsGlobalAction()
            ->addCssClass('btn btn-secondary');
    }

    // The url of a catalog row button, its csrf token in the query string - the action is a GET, which an <img> on a third-party page would otherwise fire on a logged-in admin
    private function actionUrl(string $action, TrashableInterface $entity, string $tokenId): string
    {
        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction($action)
            ->setEntityId($entity->getId())
            ->set('token', $this->csrfTokenManager->getToken($tokenId)->getValue())
            ->generateUrl();
    }

    // The catalog listing an action comes back to when it was refused
    private function indexUrl(): string
    {
        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl();
    }

    // The url of a trash row button, its csrf token in the query string - the action is a GET, which an <img> on a third-party page would otherwise fire on a logged-in admin
    private function trashActionUrl(string $action, TrashableInterface $entity, string $tokenId): string
    {
        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction($action)
            ->setEntityId($entity->getId())
            ->set('trash', 1)
            ->set('token', $this->csrfTokenManager->getToken($tokenId)->getValue())
            ->generateUrl();
    }

    // The trash listing both actions come back to, whether they ran or were refused
    private function trashIndexUrl(): string
    {
        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->set('trash', 1)
            ->generateUrl();
    }

    // Whether the index is showing the trash rather than the catalog
    private function isTrashView(): bool
    {
        return (bool) $this->requestStack->getCurrentRequest()?->query->get('trash');
    }

    // Path of the public page, null when the family isn't served here or the row has no slug - what hides the action rather than offering a dead link (see c975L\BookBundle\Service\BookPublicUrlResolver)
    private function publicPath(TrashableInterface $entity): ?string
    {
        $slug = $entity->getSlug();
        if (null === $slug || '' === $slug) {
            return null;
        }

        return $this->publicUrlResolver->resolvePath($this->displayRoute($entity), ['slug' => $slug]);
    }

    // Which public route reads a row: the family's own, unless the controller redeclares this - a serie has two, one below each index (see SerieCrudController)
    private function displayRoute(mixed $entity): string
    {
        return self::DISPLAY_ROUTE;
    }
}
