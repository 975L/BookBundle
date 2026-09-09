<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Controller\Management;

use c975L\BookBundle\Controller\Management\Trait\TrashableCrudTrait;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Form\StripMediaType;
use c975L\BookBundle\Management\BookBlockOwnerResolver;
use c975L\BookBundle\Management\StripExportProvider;
use c975L\BookBundle\Management\StripImportProvider;
use c975L\BookBundle\Service\BookCatalogExporter;
use c975L\BookBundle\Service\BookDuplicator;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\BookTrashManager;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Form\BlockType;
use c975L\UiBundle\Form\TrixEditorType;
use c975L\UiBundle\Service\BlockMoveRowAttrBuilder;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class StripCrudController extends AbstractCrudController
{
    use TrashableCrudTrait;

    // The two actions of the trash are reached by a GET, so their token travels in the url the row buttons carry (see trashActionUrl()) - a confirmation modal only holds a click back, never a request forged elsewhere
    public const string RESTORE_CSRF_TOKEN = 'book_strip_restore';
    public const string DELETE_PERMANENTLY_CSRF_TOKEN = 'book_strip_delete_permanently';
    public const string DUPLICATE_CSRF_TOKEN = 'book_strip_duplicate';

    // What the shared screen of the trash, of the exports and of the copy needs to name this very family (see Trait\TrashableCrudTrait)
    private const string DISPLAY_ROUTE = 'strip_display';
    private const string EXPORT_TABLE = 'book_strip';
    private const string EXPORT_KIND = StripImportProvider::KIND;
    private const string TRASH_BACK_LABEL = 'label.strips';
    private const string TRASH_BACK_ICON = 'fas fa-border-all';
    private const string FLASH_DUPLICATED = 'flash.strip_duplicated';
    private const string FLASH_RESTORED = 'flash.strip_restored';
    private const string FLASH_DELETED_PERMANENTLY = 'flash.strip_deleted_permanently';

    public function __construct(
        private readonly AdminContextProviderInterface $adminContextProvider,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly BlockMoveRowAttrBuilder $blockMoveRowAttrBuilder,
        private readonly BookCatalogExporter $catalogExporter,
        private readonly BookDuplicator $duplicator,
        private readonly StripExportProvider $stripExportProvider,
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
        return Strip::class;
    }

    public function configureFields(string $pageName): iterable
    {
        $entity = $this->adminContextProvider->getContext()?->getEntity()?->getInstance();
        // The planche being edited, null on the creation screen: its serie is who the characters are picked among
        $strip = $entity instanceof Strip ? $entity : null;

        return [
            // Informations
            FormField::addTab(t('label.informations', [], 'book'))
                ->hideOnIndex(),
            IntegerField::new('id')
                ->setFormTypeOption('disabled', 'disabled')
                ->hideOnIndex(),
            TextField::new('title')
                ->setLabel(t('label.title', [], 'book')),
            SlugField::new('slug')
                ->setLabel(t('label.slug', [], 'book'))
                ->hideOnIndex()
                ->setTargetFieldName('title'),
            IntegerField::new('number')
                ->setLabel(t('label.number', [], 'book')),
            AssociationField::new('serie')
                ->setLabel(t('label.serie', [], 'book'))
                ->setFormTypeOptions(['by_reference' => true])
                ->setCrudController(SerieCrudController::class)
                ->autocomplete(),
            // The switch is offered on the index too: setting a row aside and putting it back is one click there, where opening the edit screen for it is four
            BooleanField::new('hidden')
                ->setLabel(t('label.hidden', [], 'book'))
                ->setHelp(t('label.hidden-help', [], 'book')),
            DateField::new('published')
                ->setLabel(t('label.published', [], 'book')),
            // Who speaks in the planche, picked among the people its serie is peopled with rather than typed (see Entity\Character). Narrowed to that serie once the planche is filed under one: on the creation screen there is no serie yet, so the whole catalog's people are offered and who speaks is picked back on the edit screen
            // No autocomplete() here, but the field is multiple, so ChoiceAutocompleteExtension turns it into a TomSelect all the same: the guided tour outlines its row and not the clipped select (see BookGuidedProjectProvider)
            AssociationField::new('characters')
                ->setLabel(t('label.characters', [], 'book'))
                ->hideOnIndex()
                ->setFormTypeOption('by_reference', false)
                ->setQueryBuilder(
                    static fn (QueryBuilder $qb): QueryBuilder => null === $strip?->getSerie()
                        ? $qb
                        : $qb->andWhere('entity.serie = :serie')->setParameter('serie', $strip->getSerie())
                ),
            // TrixEditorType rather than EasyAdmin's own TextEditorField: its widget is where the rephrase button is wired, EasyAdmin's own rendering through a different form block
            TextareaField::new('summary')
                ->setLabel(t('label.summary', [], 'book'))
                ->hideOnIndex()
                ->setFormType(TrixEditorType::class),
            UrlField::new('sourceUrl')
                ->setLabel(t('label.source_url', [], 'book'))
                ->hideOnIndex(),

            // Dates
            DateTimeField::new('creation')
                ->setLabel(t('label.creation', [], 'book'))
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),
            DateTimeField::new('modification')
                ->setLabel(t('label.modification', [], 'book'))
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),

            // Media
            FormField::addTab(t('label.media', [], 'book'))
                ->hideOnIndex(),
            // One collection per role rather than one holding them all: the heading a file is filed under is what writes its role (see Strip::addCaseMedia()), so the page a planche was cut from is never mixed with the panels cut from it, and each pencil of the public page opens the collection its own section is drawn from (see BookEditUrlExtension)
            // The marker laid on the row is what BookGuidedProjectProvider's tour points at, a collection printing no field id of its own - it stays on the panels, which is what the tour asks the editor to attach
            $this->mediaCollection('caseMedias', 'label.strip_cases')
                ->setFormTypeOption('row_attr', ['data-strip-medias' => '1']),
            $this->mediaCollection('pageMedias', 'label.strip_page'),
            // What neither heading above claims (see Strip::getOtherMedias()). Named "Medias" and not after a role: on a site whose planches are a single picture - a reply, a gag - this is the only collection that ever holds anything, and it is the very field that screen carried before the roles existed. The only view offering the role as a choice, since it is the only one whose heading does not say it
            $this->mediaCollection('otherMedias', 'label.media', true)
                ->setHelp(t('label.strip_media_gallery-help', [], 'book')),

            // Blocks
            FormField::addTab(t('label.blocks', [], 'book'))
                ->hideOnIndex(),
            CollectionField::new('blocks')
                ->hideOnIndex()
                // CollectionField's own default is "col-md-8 col-xxl-7" - every nesting level of blocks-in-blocks eats into that same width (EasyAdmin lays each entry out as a 20% label + the rest), so the block editor is given the full row instead of 7/12 of it
                ->setColumns('col-12')
                ->setEntryType(BlockType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                ->setFormTypeOption('row_attr', $this->blockMoveRowAttrBuilder->build(BookBlockOwnerResolver::TYPE_STRIP, $entity instanceof Strip ? $entity->getId() : null)),
        ];
    }

    // The shape the three media collections of the screen share, only the role each is named after telling them apart
    private function mediaCollection(string $property, string $label, bool $kindEditable = false): CollectionField
    {
        return CollectionField::new($property)
            ->setLabel(t($label, [], 'book'))
            ->hideOnIndex()
            ->setEntryType(StripMediaType::class)
            // The dotted form and not the whole array: EasyAdmin's own configurator writes entry_options.label, .empty_data and .entityDto into it, and replacing it would drop whichever of them was set first (see CollectionConfigurator)
            ->setFormTypeOption('entry_options.kind_editable', $kindEditable)
            ->allowAdd()
            ->allowDelete()
            ->setFormTypeOption('by_reference', false)
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            // Named in the editor's own language: with no label, EasyAdmin falls back on the class name and prints "Product", "Créer Product"
            ->setEntityLabelInSingular(t('label.strip', [], 'book'))
            ->setEntityLabelInPlural(t('label.strips', [], 'book'))
            ->overrideTemplate('crud/index', '@c975LBook/management/strip_crud_index.html.twig')
            ->overrideTemplate('crud/edit', '@c975LBook/management/strip_crud_edit.html.twig')
            ->overrideTemplate('crud/new', '@c975LBook/management/strip_crud_new.html.twig')
            ->setEntityPermission($this->configService->get('site-role-editor'))
        ;
    }

    protected function duplicateEntity(mixed $entity): object
    {
        return $this->duplicator->duplicateStrip($entity);
    }

    protected function serializeSelection(array $ids): array
    {
        return $this->stripExportProvider->serializeIds($ids);
    }
}
