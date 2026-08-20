<?php

namespace c975L\BookBundle\Controller\Management;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Field\BookDataField;
use c975L\BookBundle\Form\BookMarketingType;
use c975L\BookBundle\Form\BookPresseType;
use c975L\BookBundle\Form\BookVideoType;
use c975L\BookBundle\Form\Type\BookEditionsListType;
use c975L\BookBundle\Management\BookBlockOwnerResolver;
use c975L\BookBundle\Service\BookCustomizationRegistry;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class BookCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminContextProviderInterface $adminContextProvider,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly BlockMoveRowAttrBuilder $blockMoveRowAttrBuilder,
        private readonly BookCustomizationRegistry $customizationRegistry,
        private readonly BookPublicUrlResolver $publicUrlResolver,
        private readonly ConfigServiceInterface $configService,
        private readonly Connection $connection,
        private readonly TableExporter $tableExporter,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Book::class;
    }

    public function configureFields(string $pageName): iterable
    {
        $entity = $this->adminContextProvider->getContext()?->getEntity()?->getInstance();

        return [
            // Informations
            FormField::addTab(t('label.informations', [], 'book'))
                ->hideOnIndex(),
            IntegerField::new('number')
                ->setLabel(t('label.number', [], 'book'))
                ->setHelp(t('label.number-help', [], 'book')),
            TextField::new('title')
                ->setLabel(t('label.title', [], 'book')),
            SlugField::new('slug')
                ->setLabel(t('label.slug', [], 'book'))
                ->hideOnIndex()
                ->setTargetFieldName('title'),
            TextEditorField::new('summary')
                ->hideOnIndex()
                ->setLabel(t('label.summary', [], 'book')),
            DateField::new('published')
                ->setLabel(t('label.published', [], 'book')),
            AssociationField::new('serie')
                ->setLabel(t('label.serie', [], 'book'))
                ->setFormTypeOptions([
                    'by_reference' => true,
                ])
                ->setCrudController(SerieCrudController::class)
                ->autocomplete(),
            ChoiceField::new('language')
                ->setLabel(t('label.language', [], 'book'))
                ->setTranslatableChoices([
                    'fr' => t('label.french', [], 'book'),
                    'en' => t('label.english', [], 'book'),
                    'es' => t('label.spanish', [], 'book'),
                ]),
            AssociationField::new('translationBook')
                ->setLabel(t('label.translations', [], 'book'))
                ->setFormTypeOption('query_builder', fn ($repository) => $repository->createQueryBuilder('s')
                    ->orderBy('s.title', 'ASC'))
                ->formatValue(function ($value, $entity) {
                    if (!$value) {
                        return null;
                    }

                    return sprintf('%s (%s)', $value->getTitle(), $value->getLanguage());
                }),
            TextField::new('format')
                ->setLabel(t('label.format', [], 'book')),
            NumberField::new('pages')
                ->setLabel(t('label.pages', [], 'book')),
            ...$this->dataFields(),
            // Author
            FormField::addFieldset(t('label.author', [], 'book'))
                ->hideOnIndex(),
            TextField::new('author')
                ->setLabel(t('label.author', [], 'book')),
            UrlField::new('authorWebsite')
                ->setLabel(t('label.author_website', [], 'book')),

            // Illustrator
            FormField::addFieldset(t('label.illustrator', [], 'book'))
                ->hideOnIndex(),
            TextField::new('illustrator')
                ->setLabel(t('label.illustrator', [], 'book')),
            UrlField::new('illustratorWebsite')
                    ->setLabel(t('label.illustrator_website', [], 'book')),
            // Dates
            DateTimeField::new('creation')
                ->setLabel(t('label.creation', [], 'book'))
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),
            DateTimeField::new('modification')
                ->setLabel(t('label.modification', [], 'book'))
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),

            // Technical informations
            FormField::addTab(t('label.technical_informations', [], 'book'))
                ->hideOnIndex(),
            // The versions the book is published in, listed rather than edited here: each one is a screen of its own, holding its ISBN, its files and its platforms (see c975L\BookBundle\Controller\Management\BookEditionCrudController). Rendered by the form theme's own block, which prints no field id at all - the marker on the row is what BookGuidedProjectProvider's tour points at. Named apart from the "editions" property on purpose: a field named after a to-many association is configured as a collection by EasyAdmin, whose options this plain form type knows nothing of
            Field::new('editionsList', false)
                ->setFormType(BookEditionsListType::class)
                ->setFormTypeOption('row_attr', ['data-book-editions' => '1'])
                ->hideOnIndex(),
            // Crowdfunding
            FormField::addFieldset(t('label.crowdfunding', [], 'book'))
                ->hideOnIndex(),
            TextField::new('crowdfunding')
                ->setLabel(t('label.crowdfunding', [], 'book'))
                ->hideOnIndex(),
            DateField::new('crowdfundingEndDate')
                ->setLabel(t('label.crowdfunding_end_date', [], 'book'))
                ->hideOnIndex(),

            // Media - the book's files are not here: a file belongs to the version that holds it, and is added on its screen
            FormField::addTab(t('label.media', [], 'book'))
                ->hideOnIndex(),
            // Videos
            FormField::addFieldset(t('label.videos', [], 'book'))
                ->hideOnIndex(),
            CollectionField::new('videos')
                ->hideOnIndex()
                ->setEntryType(BookVideoType::class),
            // Marketing management
            FormField::addFieldset(t('label.marketing', [], 'book'))
                ->hideOnIndex(),
            CollectionField::new('marketings')
                ->hideOnIndex()
                ->setEntryType(BookMarketingType::class),
            // Presse management
            FormField::addFieldset(t('label.presse', [], 'book'))
                ->hideOnIndex(),
            CollectionField::new('presses')
                ->hideOnIndex()
                ->setEntryType(BookPresseType::class),

            // Blocks
            FormField::addTab(t('label.blocks', [], 'book'))
                ->hideOnIndex(),
            CollectionField::new('blocks')
                ->hideOnIndex()
                // Same reasoning as StripCrudController: CollectionField's "col-md-8 col-xxl-7" default leaves a nested block editor working in 7/12 of the row
                ->setColumns('col-12')
                ->setEntryType(BlockType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                ->setFormTypeOption('row_attr', $this->blockMoveRowAttrBuilder->build(BookBlockOwnerResolver::TYPE_BOOK, $entity instanceof Book ? $entity->getId() : null)),
        ];
    }

    // What this site adds to a book and no other site has, rendered from the form type it declares - a site declaring none gets no field at all (see c975L\BookBundle\Contract\BookCustomizationProviderInterface)
    /** @return list<BookDataField> */
    private function dataFields(): array
    {
        $formType = $this->customizationRegistry->getDataFormType();

        if (null === $formType) {
            return [];
        }

        return [
            BookDataField::new('data', t('label.data', [], 'book'))
                ->setFormType($formType),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, mixed $book): void
    {
        $book->setCreation(new \DateTime());
        $book->setModification(new \DateTime());
        $book->setUser($this->getUser());

        parent::persistEntity($entityManager, $book);
    }

    public function updateEntity(EntityManagerInterface $entityManager, mixed $book): void
    {
        $book->setModification(new \DateTime());
        $book->setUser($this->getUser());

        parent::updateEntity($entityManager, $book);
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

        // Jumps straight into the versions of this book, where its ISBNs, its files and its platforms are edited (see BookEditionCrudController)
        $editionsAction = Action::new('editions', t('label.editions', [], 'book'), 'fas fa-layer-group')
            ->linkToUrl(fn (Book $book): string => $this->adminUrlGenerator
                ->setController(BookEditionCrudController::class)
                ->setAction(Action::INDEX)
                ->set('book', $book->getId())
                ->unset('entityId')
                ->generateUrl())
        ;

        // Opens the public page of the book on the site, in a new tab - hidden when this site doesn't serve that family (empty route prefix) or when the book has no slug yet
        $viewOnSiteAction = Action::new('viewOnSite', t('action.view_on_site', [], 'book'), 'fa fa-external-link-alt')
            ->linkToUrl(fn (Book $book): string => (string) $this->publicPath($book))
            ->setHtmlAttributes(['target' => '_blank'])
            ->displayIf(fn (Book $book): bool => null !== $this->publicPath($book))
            ->addCssClass('btn btn-secondary');

        // Lets the admin back out of a create/edit without saving - mirrors EasyAdmin's own built-in actions (linkToCrudAction targeting INDEX, same as Action::INDEX itself)
        $cancelAction = Action::new('cancel', $this->translator->trans('action.cancel', [], 'EasyAdminBundle'), 'fa fa-times')
            ->linkToCrudAction(Action::INDEX)
            ->addCssClass('btn btn-secondary');

        return $actions
            ->add(Crud::PAGE_INDEX, $exportGroup)
            ->add(Crud::PAGE_NEW, $cancelAction)
            ->add(Crud::PAGE_EDIT, $cancelAction)
            ->add(Crud::PAGE_INDEX, $editionsAction)
            ->add(Crud::PAGE_INDEX, $viewOnSiteAction)
            ->add(Crud::PAGE_EDIT, $viewOnSiteAction)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.edit', [], 'EasyAdminBundle'),
            ))
            ->update(Crud::PAGE_INDEX, 'editions', fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('label.editions', [], 'book'),
            ))
            ->update(Crud::PAGE_INDEX, 'viewOnSite', fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.view_on_site', [], 'book'),
            ))
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.delete', [], 'EasyAdminBundle'),
            ))
            ->reorder(Crud::PAGE_INDEX, [Action::EDIT, 'editions', 'viewOnSite', Action::DELETE])
            ->setPermission(Action::INDEX, $role)
            ->setPermission(Action::NEW, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            ->setPermission('editions', $role)
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
            // The volume number is what a catalog is read by, the latest published first - a book carrying none (a standalone title) sorts last
            ->setDefaultSort(['number' => 'DESC'])
            ->addFormTheme('@c975LBook/management/book_crud_form_theme.html.twig')
            ->overrideTemplate('crud/index', '@c975LBook/management/book_crud_index.html.twig')
            ->overrideTemplate('crud/edit', '@c975LBook/management/book_crud_edit.html.twig')
            ->overrideTemplate('crud/new', '@c975LBook/management/book_crud_new.html.twig')
            ->setEntityPermission($this->configService->get('site-role-admin'))
        ;
    }

    #[AdminRoute]
    public function exportSql(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        return $this->tableExporter->export(ExportFormat::Sql, 'book_book', $this->fetchExportRows());
    }

    #[AdminRoute]
    public function exportCsv(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        return $this->tableExporter->export(ExportFormat::Csv, 'book_book', $this->fetchExportRows());
    }

    #[AdminRoute]
    public function exportJson(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        return $this->tableExporter->export(ExportFormat::Json, 'book_book', $this->fetchExportRows());
    }

    // Path of the public page, null when the family isn't served here or the book has no slug - what hides the action rather than offering a dead link (see c975L\BookBundle\Service\BookPublicUrlResolver)
    private function publicPath(Book $book): ?string
    {
        $slug = $book->getSlug();
        if (null === $slug || '' === $slug) {
            return null;
        }

        return $this->publicUrlResolver->resolvePath('book_display', ['slug' => $slug]);
    }

    private function fetchExportRows(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM `book_book` ORDER BY `id`');
    }
}
