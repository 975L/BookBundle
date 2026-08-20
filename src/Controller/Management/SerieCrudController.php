<?php

namespace c975L\BookBundle\Controller\Management;

use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Form\SerieMediaType;
use c975L\BookBundle\Management\BookBlockOwnerResolver;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\ConfigBundle\Management\EasyAdminActionHelper;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\Export\ExportFormat;
use c975L\ConfigBundle\Service\Export\TableExporter;
use c975L\UiBundle\Form\BlockType;
use c975L\UiBundle\Service\BlockMoveRowAttrBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\ActionGroup;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class SerieCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminContextProviderInterface $adminContextProvider,
        private readonly BlockMoveRowAttrBuilder $blockMoveRowAttrBuilder,
        private readonly BookPublicUrlResolver $publicUrlResolver,
        private readonly ConfigServiceInterface $configService,
        private readonly Connection $connection,
        private readonly TableExporter $tableExporter,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Serie::class;
    }

    public function configureFields(string $pageName): iterable
    {
        $entity = $this->adminContextProvider->getContext()?->getEntity()?->getInstance();

        return [
            IntegerField::new('id')
                ->setFormTypeOption('disabled', 'disabled'),
            TextField::new('title')
                ->setLabel(t('label.title', [], 'book')),
            SlugField::new('slug')
                ->hideOnIndex()
                ->setLabel(t('label.slug', [], 'book'))
                ->setTargetFieldName('title'),
            ChoiceField::new('kind')
                ->setLabel(t('label.kind', [], 'book'))
                ->setTranslatableChoices([
                    'book' => t('label.book', [], 'book'),
                    'strip' => t('label.strip', [], 'book'),
                ]),
            TextEditorField::new('summary')
                ->hideOnIndex()
                ->setLabel(t('label.summary', [], 'book')),
            ChoiceField::new('language')
                ->setLabel(t('label.language', [], 'book'))
                ->setTranslatableChoices([
                    'fr' => t('label.french', [], 'book'),
                    'en' => t('label.english', [], 'book'),
                    'es' => t('label.spanish', [], 'book'),
                ]),

            // Author
            FormField::addFieldset(t('label.author', [], 'book'))
                ->hideOnIndex(),
            TextField::new('author')
                ->setLabel(t('label.author', [], 'book'))
                ->hideOnIndex(),
            UrlField::new('authorWebsite')
                ->setLabel(t('label.author_website', [], 'book'))
                ->hideOnIndex(),

            // Illustrator
            FormField::addFieldset(t('label.illustrator', [], 'book'))
                ->hideOnIndex(),
            TextField::new('illustrator')
                ->setLabel(t('label.illustrator', [], 'book'))
                ->hideOnIndex(),
            UrlField::new('illustratorWebsite')
                ->setLabel(t('label.illustrator_website', [], 'book'))
                ->hideOnIndex(),

            // Media management
            FormField::addFieldset(t('label.cover', [], 'book'))
                ->hideOnIndex(),
            CollectionField::new('covers')
                ->setLabel(t('label.cover', [], 'book'))
                ->setHelp(t('label.cover-help', [], 'book'))
                ->hideOnIndex()
                ->setEntryType(SerieMediaType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false),

            FormField::addFieldset(t('label.logo', [], 'book'))
                ->hideOnIndex(),
            CollectionField::new('logos')
                ->setLabel(t('label.logo', [], 'book'))
                ->setHelp(t('label.logo-help', [], 'book'))
                ->hideOnIndex()
                ->setEntryType(SerieMediaType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false),

            // Blocks
            FormField::addFieldset(t('label.blocks', [], 'book'))
                ->hideOnIndex(),
            CollectionField::new('blocks')
                ->setLabel(t('label.blocks', [], 'book'))
                ->setHelp(t('label.blocks-help', [], 'book'))
                ->hideOnIndex()
                // Same reasoning as StripCrudController: CollectionField's "col-md-8 col-xxl-7" default leaves a nested block editor working in 7/12 of the row
                ->setColumns('col-12')
                ->setEntryType(BlockType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                ->setFormTypeOption('row_attr', $this->blockMoveRowAttrBuilder->build(BookBlockOwnerResolver::TYPE_SERIE, $entity instanceof Serie ? $entity->getId() : null)),

            // Dates
            DateTimeField::new('creation')
                ->setLabel(t('label.creation', [], 'book'))
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),
            DateTimeField::new('modification')
                ->setLabel(t('label.modification', [], 'book'))
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, mixed $serie): void
    {
        $serie->setCreation(new \DateTime());
        $serie->setModification(new \DateTime());
        $serie->setUser($this->getUser());

        parent::persistEntity($entityManager, $serie);
    }

    public function updateEntity(EntityManagerInterface $entityManager, mixed $serie): void
    {
        $serie->setModification(new \DateTime());
        $serie->setUser($this->getUser());

        parent::updateEntity($entityManager, $serie);
    }

    public function configureActions(Actions $actions): Actions
    {
        $role = $this->configService->get('site-role-admin');

        $exportGroup = ActionGroup::new('export', t('label.export', [], 'book'), 'fa fa-download')
            ->createAsGlobalActionGroup()
            ->addAction(Action::new('exportSql', t('label.export_sql', [], 'book'))->linkToCrudAction('exportSql'))
            ->addAction(Action::new('exportCsv', t('label.export_csv', [], 'book'))->linkToCrudAction('exportCsv'))
            ->addAction(Action::new('exportJson', t('label.export_json', [], 'book'))->linkToCrudAction('exportJson'))
        ;

        // Opens the public page of the serie on the site, in a new tab - hidden when this site doesn't serve that family (empty route prefix) or when the serie has no slug yet
        $viewOnSiteAction = Action::new('viewOnSite', t('action.view_on_site', [], 'book'), 'fa fa-external-link-alt')
            ->linkToUrl(fn (Serie $serie): string => (string) $this->publicPath($serie))
            ->setHtmlAttributes(['target' => '_blank'])
            ->displayIf(fn (Serie $serie): bool => null !== $this->publicPath($serie))
            ->addCssClass('btn btn-secondary');

        // Lets the admin back out of a create/edit without saving - mirrors EasyAdmin's own built-in actions (linkToCrudAction targeting INDEX, same as Action::INDEX itself)
        $cancelAction = Action::new('cancel', $this->translator->trans('action.cancel', [], 'EasyAdminBundle'), 'fa fa-times')
            ->linkToCrudAction(Action::INDEX)
            ->addCssClass('btn btn-secondary');

        return $actions
            ->add(Crud::PAGE_INDEX, $exportGroup)
            ->add(Crud::PAGE_NEW, $cancelAction)
            ->add(Crud::PAGE_EDIT, $cancelAction)
            ->add(Crud::PAGE_INDEX, $viewOnSiteAction)
            ->add(Crud::PAGE_EDIT, $viewOnSiteAction)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.edit', [], 'EasyAdminBundle'),
            ))
            ->update(Crud::PAGE_INDEX, 'viewOnSite', fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.view_on_site', [], 'book'),
            ))
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.delete', [], 'EasyAdminBundle'),
            ))
            ->reorder(Crud::PAGE_INDEX, [Action::EDIT, 'viewOnSite', Action::DELETE])
            ->setPermission(Action::INDEX, $role)
            ->setPermission(Action::NEW, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            ->setPermission('viewOnSite', $role)
            ->setPermission('exportSql', $role)
            ->setPermission('exportCsv', $role)
            ->setPermission('exportJson', $role)
            // Detail adds no information beyond what edit already shows
            ->disable(Action::DETAIL)
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/index', '@c975LBook/management/serie_crud_index.html.twig')
            ->overrideTemplate('crud/edit', '@c975LBook/management/serie_crud_edit.html.twig')
            ->overrideTemplate('crud/new', '@c975LBook/management/serie_crud_new.html.twig')
            ->setEntityPermission($this->configService->get('site-role-admin'))
        ;
    }

    #[AdminRoute]
    public function exportSql(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        return $this->tableExporter->export(ExportFormat::Sql, 'book_serie', $this->fetchExportRows());
    }

    #[AdminRoute]
    public function exportCsv(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        return $this->tableExporter->export(ExportFormat::Csv, 'book_serie', $this->fetchExportRows());
    }

    #[AdminRoute]
    public function exportJson(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        return $this->tableExporter->export(ExportFormat::Json, 'book_serie', $this->fetchExportRows());
    }

    // Path of the public page, null when the family isn't served here or the serie has no slug - what hides the action rather than offering a dead link (see c975L\BookBundle\Service\BookPublicUrlResolver)
    private function publicPath(Serie $serie): ?string
    {
        $slug = $serie->getSlug();
        if (null === $slug || '' === $slug) {
            return null;
        }

        return $this->publicUrlResolver->resolvePath('serie_display', ['slug' => $slug]);
    }

    private function fetchExportRows(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM `book_serie` ORDER BY `id`');
    }
}
