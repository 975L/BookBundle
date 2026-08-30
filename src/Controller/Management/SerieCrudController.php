<?php

namespace c975L\BookBundle\Controller\Management;

use c975L\BookBundle\Controller\Management\Trait\TrashableCrudTrait;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Enum\SerieKind;
use c975L\BookBundle\Form\SerieMediaType;
use c975L\BookBundle\Management\BookBlockOwnerResolver;
use c975L\BookBundle\Management\SerieExportProvider;
use c975L\BookBundle\Management\SerieImportProvider;
use c975L\BookBundle\Service\BookDuplicator;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\BookTrashManager;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\Export\ContentExporter;
use c975L\ConfigBundle\Service\Export\TableExporter;
use c975L\UiBundle\Form\BlockType;
use c975L\UiBundle\Form\TrixEditorType;
use c975L\UiBundle\Service\BlockMoveRowAttrBuilder;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
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

class SerieCrudController extends AbstractCrudController
{
    use TrashableCrudTrait;

    // The two actions of the trash are reached by a GET, so their token travels in the url the row buttons carry (see trashActionUrl()) - a confirmation modal only holds a click back, never a request forged elsewhere
    public const string RESTORE_CSRF_TOKEN = 'book_serie_restore';
    public const string DELETE_PERMANENTLY_CSRF_TOKEN = 'book_serie_delete_permanently';
    public const string DUPLICATE_CSRF_TOKEN = 'book_serie_duplicate';

    // What the shared screen of the trash, of the exports and of the copy needs to name this very family (see Trait\TrashableCrudTrait)
    private const string DISPLAY_ROUTE = 'serie_display';
    private const string EXPORT_TABLE = 'book_serie';
    private const string EXPORT_KIND = SerieImportProvider::KIND;
    private const string TRASH_BACK_LABEL = 'label.series';
    private const string TRASH_BACK_ICON = 'fas fa-layer-group';
    private const string FLASH_DUPLICATED = 'flash.serie_duplicated';
    private const string FLASH_RESTORED = 'flash.serie_restored';
    private const string FLASH_DELETED_PERMANENTLY = 'flash.serie_deleted_permanently';

    public function __construct(
        private readonly AdminContextProviderInterface $adminContextProvider,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly BlockMoveRowAttrBuilder $blockMoveRowAttrBuilder,
        private readonly BookDuplicator $duplicator,
        private readonly SerieExportProvider $serieExportProvider,
        private readonly BookPublicUrlResolver $publicUrlResolver,
        private readonly BookTrashManager $trashManager,
        private readonly ConfigServiceInterface $configService,
        private readonly Connection $connection,
        private readonly ContentExporter $contentExporter,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly RequestStack $requestStack,
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
        // The serie being edited, null on the creation screen: its images say which slots are already taken
        $serie = $entity instanceof Serie ? $entity : null;

        return [
            IntegerField::new('id')
                ->setFormTypeOption('disabled', 'disabled'),
            // The order of the series, laid by dragging them on this screen: it is the column the handle hooks onto (see serie_crud_index.html.twig and UiBundle's assets/js/ea-index-sort.js)
            IntegerField::new('position')
                ->setLabel(t('label.position', [], 'book'))
                ->setFormTypeOption('attr', ['class' => 'ui-sort-position']),
            TextField::new('title')
                ->setLabel(t('label.title', [], 'book')),
            // The switch is offered on the index too: setting a row aside and putting it back is one click there, where opening the edit screen for it is four
            BooleanField::new('hidden')
                ->setLabel(t('label.hidden', [], 'book'))
                ->setHelp(t('label.hidden-help', [], 'book')),
            SlugField::new('slug')
                ->hideOnIndex()
                ->setLabel(t('label.slug', [], 'book'))
                ->setTargetFieldName('title'),
            // What the serie tells, which decides the index listing it (see SerieKind and StripController::index())
            ChoiceField::new('kind')
                ->setLabel(t('label.kind', [], 'book'))
                ->setTranslatableChoices([
                    SerieKind::Book->value => t(SerieKind::Book->label(), [], 'book'),
                    SerieKind::Strip->value => t(SerieKind::Strip->label(), [], 'book'),
                ]),
            // TrixEditorType rather than EasyAdmin's own TextEditorField: its widget is where the rephrase button is wired, EasyAdmin's own rendering through a different form block
            TextareaField::new('summary')
                ->hideOnIndex()
                ->setLabel(t('label.summary', [], 'book'))
                ->setFormType(TrixEditorType::class),
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
            // The marker laid on the row is what BookGuidedProjectProvider's tour points at, a collection printing no field id of its own
            CollectionField::new('covers')
                ->setLabel(t('label.cover', [], 'book'))
                ->setHelp(t('label.cover-help', [], 'book'))
                ->hideOnIndex()
                ->setEntryType(SerieMediaType::class)
                ->allowAdd(self::isEmpty($serie?->getCovers()))
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                ->setFormTypeOption('row_attr', ['data-serie-covers' => '1']),

            FormField::addFieldset(t('label.logo', [], 'book'))
                ->hideOnIndex(),
            CollectionField::new('logos')
                ->setLabel(t('label.logo', [], 'book'))
                ->setHelp(t('label.logo-help', [], 'book'))
                ->hideOnIndex()
                ->setEntryType(SerieMediaType::class)
                ->allowAdd(self::isEmpty($serie?->getLogos()))
                ->allowDelete()
                ->setFormTypeOption('by_reference', false),

            // The hero's background image, like a book's: one only, laid behind the title and veiled by the template (see Serie:Hero)
            FormField::addFieldset(t('label.background', [], 'book'))
                ->hideOnIndex(),
            CollectionField::new('backgrounds')
                ->setLabel(t('label.background', [], 'book'))
                ->setHelp(t('label.background-help', [], 'book'))
                ->hideOnIndex()
                ->setEntryType(SerieMediaType::class)
                ->allowAdd(self::isEmpty($serie?->getBackgrounds()))
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

    // A serie has one cover and one logo only: "Add an item" under the one already laid invited a second nothing would have shown. Read on the row as it stands in database, so the link comes back on saving a removal - laying another file on the existing row stays the gesture replacing an image
    /** @param Collection<int, mixed>|null $medias */
    private static function isEmpty(?Collection $medias): bool
    {
        return null === $medias || $medias->isEmpty();
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            // Named in the editor's own language: with no label, EasyAdmin falls back on the class name and prints "Product", "Créer Product"
            ->setEntityLabelInSingular(t('label.serie', [], 'book'))
            ->setEntityLabelInPlural(t('label.series', [], 'book'))
            // In the order the editor laid, the very one the public pages follow (see SerieRepository)
            ->setDefaultSort(['position' => 'ASC'])
            ->overrideTemplate('crud/index', '@c975LBook/management/serie_crud_index.html.twig')
            ->overrideTemplate('crud/edit', '@c975LBook/management/serie_crud_edit.html.twig')
            ->overrideTemplate('crud/new', '@c975LBook/management/serie_crud_new.html.twig')
            ->setEntityPermission($this->configService->get('site-role-editor'))
        ;
    }

    // Deleting only takes the serie off the site: it goes to the trash, where it can be brought back or removed for good. Refused while it still holds a book or a strip - what it holds would otherwise stay on the site naming a serie that is not on it any more, and the foreign key would refuse the day the serie is removed for good. Trashed books and strips count just as much: they name it too
    // Saves a new order for the series, as the drag and drop asks (see serie_crud_index.html.twig and UiBundle's assets/js/ea-index-sort.js). The ids received are read back from database rather than taken on trust
    #[AdminRoute(path: '/reorder', options: ['methods' => ['POST']])]
    public function reorder(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        $payload = json_decode($request->getContent(), true) ?? [];
        if (!$this->isCsrfTokenValid('serie_reorder', $payload['_token'] ?? null)) {
            throw $this->createAccessDeniedException();
        }

        $ids = array_map(intval(...), (array) ($payload['ids'] ?? []));
        $series = [];
        foreach ($entityManager->getRepository(Serie::class)->findBy(['id' => $ids]) as $serie) {
            $series[$serie->getId()] = $serie;
        }

        $positions = [];
        foreach (array_values($ids) as $position => $id) {
            if (isset($series[$id])) {
                $series[$id]->setPosition($position);
                $positions[$id] = $position;
            }
        }

        $entityManager->flush();

        // What was saved, so the screen shows the new numbers without being reloaded
        return new JsonResponse(['positions' => $positions]);
    }

    // The index switch goes through here and not through the form: the refusal has to answer with an error, which is what puts the switch back and greys it out (see EasyAdmin's assets/js/field-boolean.js), a 200 leaving the row saying the serie was hidden when it was not
    protected function ajaxEdit(EntityDto $entityDto, ?string $propertyName, bool $newValue): AfterCrudActionEvent
    {
        $serie = $entityDto->getInstance();

        if ('hidden' === $propertyName && $newValue && $serie instanceof Serie && $serie->holdsVisibleContent()) {
            throw new \RuntimeException($this->translator->trans('flash.serie_holds_shown_content', [], 'book'));
        }

        return parent::ajaxEdit($entityDto, $propertyName, $newValue);
    }

    // Refused for the very reason the trash is (see deleteEntity below): the books and the planches it holds would stay on the site, each naming a serie whose own page has just started answering 404 - which the "urls-book" health check reports as a broken link. The switch is put back rather than the save refused, so the edit form shows what was actually stored - the index switch is refused earlier, in ajaxEdit() above
    public function updateEntity(EntityManagerInterface $entityManager, mixed $serie): void
    {
        if ($serie instanceof Serie && $serie->isHidden() && $serie->holdsVisibleContent()) {
            $serie->setHidden(false);
            $this->addFlash('danger', $this->translator->trans('flash.serie_holds_shown_content', [], 'book'));
        }

        parent::updateEntity($entityManager, $serie);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, mixed $serie): void
    {
        if ($serie->holdsContent()) {
            $this->addFlash('danger', $this->translator->trans('flash.serie_holds_content', [], 'book'));

            return;
        }

        $this->trashManager->moveToTrash($serie);
    }

    protected function duplicateEntity(mixed $entity): object
    {
        return $this->duplicator->duplicateSerie($entity);
    }

    protected function serializeSelection(array $ids): array
    {
        return $this->serieExportProvider->serializeIds($ids);
    }

    // A serie is read below the index listing its kind, so this family wears two routes where the others wear one (see BookPublicUrlResolver::serieRoute())
    private function displayRoute(mixed $entity): string
    {
        return $entity instanceof Serie ? BookPublicUrlResolver::serieRoute($entity) : self::DISPLAY_ROUTE;
    }
}
