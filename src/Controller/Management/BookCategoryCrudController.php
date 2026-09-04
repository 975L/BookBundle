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
use c975L\BookBundle\Entity\BookCategory;
use c975L\BookBundle\Management\BookBlockOwnerResolver;
use c975L\BookBundle\Management\BookCategoryExportProvider;
use c975L\BookBundle\Management\BookCategoryImportProvider;
use c975L\BookBundle\Service\BookCatalogExporter;
use c975L\BookBundle\Service\BookDuplicator;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\BookTrashManager;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Form\BlockType;
use c975L\UiBundle\Form\TrixEditorType;
use c975L\UiBundle\Service\BlockMoveRowAttrBuilder;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class BookCategoryCrudController extends AbstractCrudController
{
    use TrashableCrudTrait;

    // The two actions of the trash are reached by a GET, so their token travels in the url the row buttons carry (see trashActionUrl()) - a confirmation modal only holds a click back, never a request forged elsewhere
    public const string RESTORE_CSRF_TOKEN = 'book_category_restore';
    public const string DELETE_PERMANENTLY_CSRF_TOKEN = 'book_category_delete_permanently';
    public const string DUPLICATE_CSRF_TOKEN = 'book_category_duplicate';

    // What the shared screen of the trash, of the exports and of the copy needs to name this very family (see Trait\TrashableCrudTrait)
    private const string DISPLAY_ROUTE = 'book_category_display';
    private const string EXPORT_TABLE = 'book_category';
    private const string EXPORT_KIND = BookCategoryImportProvider::KIND;
    private const string TRASH_BACK_LABEL = 'label.categories';
    private const string TRASH_BACK_ICON = 'fas fa-tags';
    private const string FLASH_DUPLICATED = 'flash.category_duplicated';
    private const string FLASH_RESTORED = 'flash.category_restored';
    private const string FLASH_DELETED_PERMANENTLY = 'flash.category_deleted_permanently';

    public function __construct(
        private readonly AdminContextProviderInterface $adminContextProvider,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly BlockMoveRowAttrBuilder $blockMoveRowAttrBuilder,
        private readonly BookCatalogExporter $catalogExporter,
        private readonly BookCategoryExportProvider $categoryExportProvider,
        private readonly BookDuplicator $duplicator,
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
        return BookCategory::class;
    }

    // A declaration of fields, one line per field: its length says how much the screen shows, not how much the method decides
    /** @SuppressWarnings(PHPMD.ExcessiveMethodLength) */
    public function configureFields(string $pageName): iterable
    {
        $entity = $this->adminContextProvider->getContext()?->getEntity()?->getInstance();

        return [
            IntegerField::new('id')
                ->setFormTypeOption('disabled', 'disabled'),
            // The order the categories show in, laid by dragging them on this screen: it is the column the handle hooks onto (see category_crud_index.html.twig and UiBundle's assets/js/ea-index-sort.js)
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
            // TrixEditorType rather than EasyAdmin's own TextEditorField: its widget is where the rephrase button is wired, EasyAdmin's own rendering through a different form block. Above the code, which is the order the guided project walks the screen in: a subject is written out before it is filed under a trade nomenclature, and the serie's screen reads the same way
            TextareaField::new('summary')
                ->hideOnIndex()
                ->setLabel(t('label.summary', [], 'book'))
                ->setHelp(t('label.category_summary-help', [], 'book'))
                ->setFormType(TrixEditorType::class),
            // The code the trade files this subject under - CLIL here, Thema or BISAC abroad - asked of a publisher distributing through a wholesaler. Stored and never interpreted: a site publishing on its own leaves it empty
            TextField::new('code')
                ->setLabel(t('label.category_code', [], 'book'))
                ->setHelp(t('label.category_code-help', [], 'book')),

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
                ->setFormTypeOption('row_attr', $this->blockMoveRowAttrBuilder->build(BookBlockOwnerResolver::TYPE_CATEGORY, $entity instanceof BookCategory ? $entity->getId() : null)),

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

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            // Named in the editor's own language: with no label, EasyAdmin falls back on the class name and prints "BookCategory"
            ->setEntityLabelInSingular(t('label.category', [], 'book'))
            ->setEntityLabelInPlural(t('label.categories', [], 'book'))
            // In the order the editor laid, the very one the public pages follow (see BookCategoryRepository)
            ->setDefaultSort(['position' => 'ASC'])
            ->overrideTemplate('crud/index', '@c975LBook/management/category_crud_index.html.twig')
            ->overrideTemplate('crud/edit', '@c975LBook/management/category_crud_edit.html.twig')
            ->overrideTemplate('crud/new', '@c975LBook/management/category_crud_new.html.twig')
            ->setEntityPermission($this->configService->get('site-role-editor'))
        ;
    }

    // Saves a new order for the categories, as the drag and drop asks (see category_crud_index.html.twig and UiBundle's assets/js/ea-index-sort.js). The ids received are read back from database rather than taken on trust
    #[AdminRoute(path: '/reorder', options: ['methods' => ['POST']])]
    public function reorder(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        $payload = json_decode($request->getContent(), true) ?? [];
        if (!$this->isCsrfTokenValid('book_category_reorder', $payload['_token'] ?? null)) {
            throw $this->createAccessDeniedException();
        }

        $ids = array_map(intval(...), (array) ($payload['ids'] ?? []));
        $categories = [];
        foreach ($entityManager->getRepository(BookCategory::class)->findBy(['id' => $ids]) as $category) {
            $categories[$category->getId()] = $category;
        }

        $positions = [];
        foreach (array_values($ids) as $position => $id) {
            if (isset($categories[$id])) {
                $categories[$id]->setPosition($position);
                $positions[$id] = $position;
            }
        }

        $entityManager->flush();

        // What was saved, so the screen shows the new numbers without being reloaded
        return new JsonResponse(['positions' => $positions]);
    }

    protected function duplicateEntity(mixed $entity): object
    {
        return $this->duplicator->duplicateCategory($entity);
    }

    protected function serializeSelection(array $ids): array
    {
        return $this->categoryExportProvider->serializeIds($ids);
    }
}
