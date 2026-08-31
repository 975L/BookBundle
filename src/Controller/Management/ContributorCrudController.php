<?php

namespace c975L\BookBundle\Controller\Management;

use c975L\BookBundle\Controller\Management\Trait\TrashableCrudTrait;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Form\ContributorMediaType;
use c975L\BookBundle\Management\BookBlockOwnerResolver;
use c975L\BookBundle\Management\ContributorExportProvider;
use c975L\BookBundle\Management\ContributorImportProvider;
use c975L\BookBundle\Service\BookCatalogExporter;
use c975L\BookBundle\Service\BookDuplicator;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\BookTrashManager;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Form\BlockType;
use c975L\UiBundle\Form\TrixEditorType;
use c975L\UiBundle\Service\BlockMoveRowAttrBuilder;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class ContributorCrudController extends AbstractCrudController
{
    // updateEntity() and createIndexQueryBuilder() are widened below: a method written in the class body would take the trait's place instead of chaining to it
    use TrashableCrudTrait {
        createIndexQueryBuilder as private trashableIndexQueryBuilder;
        updateEntity as private trashableUpdateEntity;
    }

    // The two actions of the trash are reached by a GET, so their token travels in the url the row buttons carry (see trashActionUrl()) - a confirmation modal only holds a click back, never a request forged elsewhere
    public const string RESTORE_CSRF_TOKEN = 'book_contributor_restore';
    public const string DELETE_PERMANENTLY_CSRF_TOKEN = 'book_contributor_delete_permanently';
    public const string DUPLICATE_CSRF_TOKEN = 'book_contributor_duplicate';

    // What the shared screen of the trash, of the exports and of the copy needs to name this very family (see Trait\TrashableCrudTrait)
    private const string DISPLAY_ROUTE = 'contributor_display';
    private const string EXPORT_TABLE = 'book_contributor';
    private const string EXPORT_KIND = ContributorImportProvider::KIND;
    private const string TRASH_BACK_LABEL = 'label.contributors';
    private const string TRASH_BACK_ICON = 'fas fa-user-pen';
    private const string FLASH_DUPLICATED = 'flash.contributor_duplicated';
    private const string FLASH_RESTORED = 'flash.contributor_restored';
    private const string FLASH_DELETED_PERMANENTLY = 'flash.contributor_deleted_permanently';

    public function __construct(
        private readonly AdminContextProviderInterface $adminContextProvider,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly BlockMoveRowAttrBuilder $blockMoveRowAttrBuilder,
        private readonly BookCatalogExporter $catalogExporter,
        private readonly BookDuplicator $duplicator,
        private readonly ContributorExportProvider $contributorExportProvider,
        private readonly BookPublicUrlResolver $publicUrlResolver,
        private readonly BookTrashManager $trashManager,
        private readonly ConfigServiceInterface $configService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Contributor::class;
    }

    // A declaration of fields, one line per field: its length says how much the screen shows, not how much the method decides
    /** @SuppressWarnings(PHPMD.ExcessiveMethodLength) */
    public function configureFields(string $pageName): iterable
    {
        $entity = $this->adminContextProvider->getContext()?->getEntity()?->getInstance();
        // The person being edited, null on the creation screen: their images say which slots are already taken
        $contributor = $entity instanceof Contributor ? $entity : null;

        return [
            IntegerField::new('id')
                ->setFormTypeOption('disabled', 'disabled'),
            // The order they show in, laid by dragging them on this screen: it is the column the handle hooks onto (see contributor_crud_index.html.twig and UiBundle's assets/js/ea-index-sort.js)
            IntegerField::new('position')
                ->setLabel(t('label.position', [], 'book'))
                ->setFormTypeOption('attr', ['class' => 'ui-sort-position']),
            TextField::new('name')
                ->setLabel(t('label.name', [], 'book')),
            // The switch is offered on the index too: setting a row aside and putting it back is one click there, where opening the edit screen for it is four
            BooleanField::new('hidden')
                ->setLabel(t('label.hidden', [], 'book'))
                ->setHelp(t('label.hidden-help', [], 'book')),
            SlugField::new('slug')
                ->hideOnIndex()
                ->setLabel(t('label.slug', [], 'book'))
                ->setTargetFieldName('name'),
            // TrixEditorType rather than EasyAdmin's own TextEditorField: its widget is where the rephrase button is wired, EasyAdmin's own rendering through a different form block
            TextareaField::new('summary')
                ->hideOnIndex()
                ->setLabel(t('label.summary', [], 'book'))
                ->setHelp(t('label.contributor_summary-help', [], 'book'))
                ->setFormType(TrixEditorType::class),
            UrlField::new('website')
                ->setLabel(t('label.website', [], 'book'))
                ->hideOnIndex(),

            // Media management
            FormField::addFieldset(t('label.portrait', [], 'book'))
                ->hideOnIndex(),
            // The marker laid on the row is what BookGuidedProjectProvider's tour points at, a collection printing no field id of its own
            CollectionField::new('portraits')
                ->setLabel(t('label.portrait', [], 'book'))
                ->setHelp(t('label.portrait-help', [], 'book'))
                ->hideOnIndex()
                ->setEntryType(ContributorMediaType::class)
                ->allowAdd(self::isEmpty($contributor?->getPortraits()))
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                ->setFormTypeOption('row_attr', ['data-contributor-portraits' => '1']),

            // The hero's background image, like a serie's: one only, laid behind the name and veiled by the template (see Contributor:Hero)
            FormField::addFieldset(t('label.background', [], 'book'))
                ->hideOnIndex(),
            CollectionField::new('backgrounds')
                ->setLabel(t('label.background', [], 'book'))
                ->setHelp(t('label.background-help', [], 'book'))
                ->hideOnIndex()
                ->setEntryType(ContributorMediaType::class)
                ->allowAdd(self::isEmpty($contributor?->getBackgrounds()))
                ->allowDelete()
                ->setFormTypeOption('by_reference', false),

            // Blocks
            FormField::addFieldset(t('label.blocks', [], 'book'))
                ->hideOnIndex(),
            CollectionField::new('blocks')
                ->setLabel(t('label.blocks', [], 'book'))
                ->setHelp(t('label.blocks-help', [], 'book'))
                ->hideOnIndex()
                // Same reasoning as SerieCrudController: CollectionField's "col-md-8 col-xxl-7" default leaves a nested block editor working in 7/12 of the row
                ->setColumns('col-12')
                ->setEntryType(BlockType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                ->setFormTypeOption('row_attr', $this->blockMoveRowAttrBuilder->build(BookBlockOwnerResolver::TYPE_CONTRIBUTOR, $entity instanceof Contributor ? $entity->getId() : null)),

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

    // One portrait and one background only: "Add an item" under the one already laid invited a second nothing would have shown. Read on the row as it stands in database, so the link comes back on saving a removal - laying another file on the existing row stays the gesture replacing an image
    /** @param Collection<int, mixed>|null $medias */
    private static function isEmpty(?Collection $medias): bool
    {
        return null === $medias || $medias->isEmpty();
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            // Named in the editor's own language: with no label, EasyAdmin falls back on the class name and prints "Contributor", "Créer Contributor"
            ->setEntityLabelInSingular(t('label.contributor', [], 'book'))
            ->setEntityLabelInPlural(t('label.contributors', [], 'book'))
            // In the order the editor laid, the very one the public pages follow (see ContributorRepository)
            ->setDefaultSort(['position' => 'ASC'])
            ->overrideTemplate('crud/index', '@c975LBook/management/contributor_crud_index.html.twig')
            ->overrideTemplate('crud/edit', '@c975LBook/management/contributor_crud_edit.html.twig')
            ->overrideTemplate('crud/new', '@c975LBook/management/contributor_crud_new.html.twig')
            ->setEntityPermission($this->configService->get('site-role-editor'))
        ;
    }

    // Saves a new order for the people, as the drag and drop asks (see contributor_crud_index.html.twig and UiBundle's assets/js/ea-index-sort.js). The ids received are read back from database rather than taken on trust
    #[AdminRoute(path: '/reorder', options: ['methods' => ['POST']])]
    public function reorder(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        $payload = json_decode($request->getContent(), true) ?? [];
        if (!$this->isCsrfTokenValid('contributor_reorder', $payload['_token'] ?? null)) {
            throw $this->createAccessDeniedException();
        }

        $ids = array_map(intval(...), (array) ($payload['ids'] ?? []));
        $contributors = [];
        foreach ($entityManager->getRepository(Contributor::class)->findBy(['id' => $ids]) as $contributor) {
            $contributors[$contributor->getId()] = $contributor;
        }

        $positions = [];
        foreach (array_values($ids) as $position => $id) {
            if (isset($contributors[$id])) {
                $contributors[$id]->setPosition($position);
                $positions[$id] = $position;
            }
        }

        $entityManager->flush();

        // What was saved, so the screen shows the new numbers without being reloaded
        return new JsonResponse(['positions' => $positions]);
    }

    // The index switch goes through here and not through the form: the refusal has to answer with an error, which is what puts the switch back and greys it out (see EasyAdmin's assets/js/field-boolean.js), a 200 leaving the row saying the person was hidden when they were not
    protected function ajaxEdit(EntityDto $entityDto, ?string $propertyName, bool $newValue): AfterCrudActionEvent
    {
        $contributor = $entityDto->getInstance();

        if ('hidden' === $propertyName && $newValue && $contributor instanceof Contributor && $contributor->holdsVisibleContent()) {
            throw new \RuntimeException($this->translator->trans('flash.contributor_holds_shown_content', [], 'book'));
        }

        return parent::ajaxEdit($entityDto, $propertyName, $newValue);
    }

    // Refused for the very reason the trash is (see deleteEntity below): the books and the series crediting them would stay on the site, each naming someone whose own page has just started answering 404 - which the "urls-book" health check reports as a broken link. The switch is put back rather than the save refused, so the edit form shows what was actually stored - the index switch is refused earlier, in ajaxEdit() above
    public function updateEntity(EntityManagerInterface $entityManager, mixed $contributor): void
    {
        if ($contributor instanceof Contributor && $contributor->isHidden() && $contributor->holdsVisibleContent()) {
            $contributor->setHidden(false);
            $this->addFlash('danger', $this->translator->trans('flash.contributor_holds_shown_content', [], 'book'));
        }

        $this->trashableUpdateEntity($entityManager, $contributor);
    }

    // The autocompletion of the author and illustrator fields of a book reads this very query (see BookCrudController), and a hidden person must not be creditable there: their public page answers 404. Narrowed to that request alone, as the back-office index has to keep showing them
    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters,
    ): QueryBuilder {
        $queryBuilder = $this->trashableIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        if ($this->requestStack->getCurrentRequest()?->query->has(AssociationField::PARAM_AUTOCOMPLETE_CONTEXT)) {
            $queryBuilder->andWhere('entity.hidden = false');
        }

        return $queryBuilder;
    }

    // Refused while a book or a serie still credits them - what credits them would otherwise keep naming someone the catalog has lost, and the foreign key would refuse the day they are removed for good. Trashed rows count just as much: they name them too
    public function deleteEntity(EntityManagerInterface $entityManager, mixed $contributor): void
    {
        if ($contributor->holdsContent()) {
            $this->addFlash('danger', $this->translator->trans('flash.contributor_holds_content', [], 'book'));

            return;
        }

        $this->trashManager->moveToTrash($contributor);
    }

    protected function duplicateEntity(mixed $entity): object
    {
        return $this->duplicator->duplicateContributor($entity);
    }

    protected function serializeSelection(array $ids): array
    {
        return $this->contributorExportProvider->serializeIds($ids);
    }
}
