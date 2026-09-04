<?php

namespace c975L\BookBundle\Controller\Management;

use c975L\BookBundle\Controller\Management\Trait\TrashableCrudTrait;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Field\BookDataField;
use c975L\BookBundle\Form\BookContributorType;
use c975L\BookBundle\Form\BookCoverType;
use c975L\BookBundle\Form\BookEditionType;
use c975L\BookBundle\Form\BookFlipbookType;
use c975L\BookBundle\Form\BookLinkType;
use c975L\BookBundle\Form\BookMarketingType;
use c975L\BookBundle\Form\BookMediaType;
use c975L\BookBundle\Form\BookPresseType;
use c975L\BookBundle\Form\BookVideoType;
use c975L\BookBundle\Management\BookBlockOwnerResolver;
use c975L\BookBundle\Management\BookExportProvider;
use c975L\BookBundle\Management\BookImportProvider;
use c975L\BookBundle\Service\BookCatalogExporter;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use c975L\BookBundle\Service\BookDuplicator;
use c975L\BookBundle\Service\BookMediaMoveRowAttrBuilder;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\BookTrashManager;
use c975L\BookBundle\Service\BookVersionPublisher;
use c975L\ConfigBundle\Management\EasyAdminActionHelper;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Form\BlockType;
use c975L\UiBundle\Form\TrixEditorType;
use c975L\UiBundle\Service\BlockMoveRowAttrBuilder;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class BookCrudController extends AbstractCrudController
{
    // configureActions() is widened below with an action of its own to books: only a book comes in versions
    use TrashableCrudTrait {
        configureActions as private trashableActions;
    }

    // The two actions of the trash are reached by a GET, so their token travels in the url the row buttons carry (see trashActionUrl()) - a confirmation modal only holds a click back, never a request forged elsewhere
    public const string RESTORE_CSRF_TOKEN = 'book_book_restore';
    public const string DELETE_PERMANENTLY_CSRF_TOKEN = 'book_book_delete_permanently';
    public const string DUPLICATE_CSRF_TOKEN = 'book_book_duplicate';
    public const string PUBLISH_VERSION_CSRF_TOKEN = 'book_book_publish_version';

    // What the shared screen of the trash, of the exports and of the copy needs to name this very family (see Trait\TrashableCrudTrait)
    private const string DISPLAY_ROUTE = 'book_display';
    private const string EXPORT_TABLE = 'book_book';
    private const string EXPORT_KIND = BookImportProvider::KIND;
    private const string TRASH_BACK_LABEL = 'label.books';
    private const string TRASH_BACK_ICON = 'fas fa-book';
    private const string FLASH_DUPLICATED = 'flash.book_duplicated';
    private const string FLASH_RESTORED = 'flash.book_restored';
    private const string FLASH_DELETED_PERMANENTLY = 'flash.book_deleted_permanently';

    public function __construct(
        private readonly AdminContextProviderInterface $adminContextProvider,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly BlockMoveRowAttrBuilder $blockMoveRowAttrBuilder,
        private readonly BookCatalogExporter $catalogExporter,
        private readonly BookCustomizationRegistry $customizationRegistry,
        private readonly BookDuplicator $duplicator,
        private readonly BookExportProvider $bookExportProvider,
        private readonly BookMediaMoveRowAttrBuilder $mediaMoveRowAttrBuilder,
        private readonly BookPublicUrlResolver $publicUrlResolver,
        private readonly BookTrashManager $trashManager,
        private readonly BookVersionPublisher $versionPublisher,
        private readonly ConfigServiceInterface $configService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly RequestStack $requestStack,
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
        // The book being edited, null on the "new" screen: its id is carried by the file collections so their rows can be swapped by drag-and-drop (see BookMediaMoveRowAttrBuilder), and its images say which slots are already filled
        $book = $entity instanceof Book ? $entity : null;
        $bookId = $book?->getId();

        return [
            ...$this->informationFields(),
            ...$this->pageFields($book, $bookId),
            ...$this->blockFields($entity),
        ];
    }

    // The "informations" tab: what the book is, who made it, and the series it belongs to
    private function informationFields(): array
    {
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
            // TrixEditorType rather than EasyAdmin's own TextEditorField: its widget is where the rephrase button is wired, EasyAdmin's own rendering through a different form block
            TextareaField::new('summary')
                ->hideOnIndex()
                ->setLabel(t('label.summary', [], 'book'))
                ->setFormType(TrixEditorType::class),
            DateField::new('published')
                ->setLabel(t('label.published', [], 'book')),
            // The switch is offered on the index too: setting a row aside and putting it back is one click there, where opening the edit screen for it is four
            BooleanField::new('hidden')
                ->setLabel(t('label.hidden', [], 'book'))
                ->setHelp(t('label.hidden-help', [], 'book')),
            AssociationField::new('serie')
                ->setLabel(t('label.serie', [], 'book'))
                ->setFormTypeOptions([
                    'by_reference' => true,
                ])
                ->setCrudController(SerieCrudController::class)
                ->autocomplete(),
            // What the book is about, where the serie above says what it belongs to: as many as it deserves, none being just as valid (see Entity\BookCategory). Hidden from the index, where a row of tags would take the width the title needs
            AssociationField::new('categories')
                ->setLabel(t('label.categories', [], 'book'))
                ->setHelp(t('label.categories-help', [], 'book'))
                ->hideOnIndex()
                ->setFormTypeOptions([
                    'by_reference' => false,
                ])
                ->setCrudController(BookCategoryCrudController::class)
                ->autocomplete(),
            ChoiceField::new('language')
                ->setLabel(t('label.language', [], 'book'))
                ->setTranslatableChoices([
                    'fr' => t('label.french', [], 'book'),
                    'en' => t('label.english', [], 'book'),
                    'es' => t('label.spanish', [], 'book'),
                ]),
            // The age the book is read at, in the form schema.org reads it: the value fills the page's typicalAgeRange as much as the line the informations show (see BookSnippetBuilder)
            TextField::new('age')
                ->setLabel(t('label.age', [], 'book'))
                ->setHelp(t('label.age-help', [], 'book'))
                ->hideOnIndex(),
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
            // The book replacing this one: a newly illustrated, revised or recomposed version comes out as a book of its own, with its ISBN and its page (see Book::$newerVersion) - filled in, this book leaves the catalog and is reached from the one replacing it
            AssociationField::new('newerVersion')
                ->setLabel(t('label.newer_version', [], 'book'))
                ->setHelp(t('label.newer_version-help', [], 'book'))
                ->hideOnIndex()
                ->setFormTypeOption('query_builder', $this->versionQueryBuilder())
                ->formatValue(fn ($value) => $value instanceof Book ? $value->getTitle() : null),
            // The two ends of the same link: it is unique and reads from either side, and filling it here saves opening the other book to say what this one replaces
            AssociationField::new('previousVersion')
                ->setLabel(t('label.previous_version', [], 'book'))
                ->setHelp(t('label.previous_version-help', [], 'book'))
                ->hideOnIndex()
                ->setFormTypeOption('query_builder', $this->versionQueryBuilder())
                ->formatValue(fn ($value) => $value instanceof Book ? $value->getTitle() : null),
            ...$this->dataFields(),
            ...$this->creditFields(),
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

    // Who made the book, under the "informations" tab: the two the catalog holds in columns of its own, then one row per other part
    private function creditFields(): array
    {
        return [
            // Author and illustrator, picked among the people the site records rather than retyped on each book: the site each is read on travels with them, and a spelling apart no longer makes a second author (see Entity\Contributor). Left empty, a book in a serie credits whoever the serie does (see Book::getEffectiveAuthor())
            FormField::addFieldset(t('label.author', [], 'book'))
                ->hideOnIndex(),
            AssociationField::new('author')
                ->setLabel(t('label.author', [], 'book'))
                ->setHelp(t('label.author-help', [], 'book'))
                ->autocomplete(),

            FormField::addFieldset(t('label.illustrator', [], 'book'))
                ->hideOnIndex(),
            AssociationField::new('illustrator')
                ->setLabel(t('label.illustrator', [], 'book'))
                ->setHelp(t('label.illustrator-help', [], 'book'))
                ->autocomplete(),

            // Everyone else the book credits - the voice that read it, the pen that carried it into another language - one row per part rather than one field per role: a catalog crediting a colourist names it in its own vocabulary and no column is added to it (see BookContributorType)
            FormField::addFieldset(t('label.book_contributors', [], 'book'))
                ->hideOnIndex(),
            CollectionField::new('contributors')
                ->setLabel(false)
                ->setHelp(t('label.book_contributors-help', [], 'book'))
                ->hideOnIndex()
                ->setEntryType(BookContributorType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                // The marker laid on the row is what BookGuidedProjectProvider's tour points at, a collection printing no field id of its own
                ->setFormTypeOption('row_attr', ['data-book-contributors' => '1']),
        ];
    }

    // The "page" tab: the very sections a book's page lays out, in the order it prints them (see BookSectionsExtension) - the header it opens on, its videos, its versions, then what comes under them. The images and files the public page is built from, each collection carrying the book's id so its rows can be swapped by drag-and-drop: where a file or a platform is written is where it is read. A declaration of fields, one line per field: its length says how much the screen shows, not how much the method decides
    /** @SuppressWarnings(PHPMD.ExcessiveMethodLength) */
    private function pageFields(?Book $book, ?int $bookId): array
    {
        return [
            FormField::addTab(t('label.page', [], 'book'))
                ->hideOnIndex(),
            // Header - three fields rather than one collection asking which of the three a file is: the field it is dropped on is what says so (see Book::addCover() and its two siblings). Each holds one image only, hence allowAdd() closed as soon as it is there (see isEmpty()): a book has one cover, and "Add an item" under the one already laid invited a second nothing would have shown
            FormField::addFieldset(t('label.hero', [], 'book'))
                ->hideOnIndex(),
            CollectionField::new('covers')
                ->setLabel(t('label.cover', [], 'book'))
                ->setHelp(t('label.book_cover-help', [], 'book'))
                ->hideOnIndex()
                ->setEntryType(BookCoverType::class)
                ->allowAdd(self::isEmpty($book?->getCovers()))
                ->allowDelete()
                ->setFormTypeOption('by_reference', false),
            CollectionField::new('backCovers')
                ->setLabel(t('label.back_cover', [], 'book'))
                ->setHelp(t('label.back_cover-help', [], 'book'))
                ->hideOnIndex()
                ->setEntryType(BookCoverType::class)
                ->allowAdd(self::isEmpty($book?->getBackCovers()))
                ->allowDelete()
                ->setFormTypeOption('by_reference', false),
            CollectionField::new('backgrounds')
                ->setLabel(t('label.background', [], 'book'))
                ->setHelp(t('label.background-help', [], 'book'))
                ->hideOnIndex()
                ->setEntryType(BookCoverType::class)
                ->allowAdd(self::isEmpty($book?->getBackgrounds()))
                ->allowDelete()
                ->setFormTypeOption('by_reference', false),
            // The flipbook, between the hero's backdrop and the extracts: a video, hence its own row type
            FormField::addFieldset(t('label.flipbook', [], 'book'))
                ->hideOnIndex(),
            CollectionField::new('flipbooks')
                ->setLabel(t('label.flipbook', [], 'book'))
                ->setHelp(t('label.flipbook-help', [], 'book'))
                ->hideOnIndex()
                ->setEntryType(BookFlipbookType::class)
                ->allowAdd(self::isEmpty($book?->getFlipbooks()))
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                ->setFormTypeOption('row_attr', $this->mediaMoveRowAttrBuilder->build($bookId, BookMediaMoveRowAttrBuilder::TARGET_FLIPBOOK)),
            // The extracts: what the page runs through a slider, after the hero's backdrop. Named by the verb of its section, like the others: the edit screen reads in the page's order and under the same words (see BookSectionsExtension::book()) - the field itself keeps the name of what is dropped in it, pages
            FormField::addFieldset(t('label.read', [], 'book'))
                ->hideOnIndex(),
            CollectionField::new('extracts')
                ->setLabel(t('label.extract', [], 'book'))
                ->setHelp(t('label.extract-help', [], 'book'))
                ->hideOnIndex()
                ->setEntryType(BookCoverType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                ->setFormTypeOption('row_attr', $this->mediaMoveRowAttrBuilder->build($bookId, BookMediaMoveRowAttrBuilder::TARGET_EXTRACT)),
            // "Listen" - the book's recording and the apps it is followed on, in the page's order. The files themselves are the book's audio medias: they belong to the book and not to its audio edition, which only says the ISBN it comes out under
            FormField::addFieldset(t('label.podcasts', [], 'book'))
                ->hideOnIndex(),
            CollectionField::new('audios')
                ->setLabel(t('label.media', [], 'book'))
                ->setHelp(t('label.audios-help', [], 'book'))
                ->hideOnIndex()
                ->setEntryType(BookMediaType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false),
            CollectionField::new('listenLinks')
                ->setLabel(t('label.links', [], 'book'))
                ->setHelp(t('label.listen_links-help', [], 'book'))
                ->hideOnIndex()
                ->setEntryType(BookLinkType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false),
            // "Watch" - the same section of the page. Like the collections following it, each is alone of its kind in its fieldset
            FormField::addFieldset(t('label.watch', [], 'book'))
                ->hideOnIndex(),
            CollectionField::new('videos')
                ->setLabel(t('label.videos', [], 'book'))
                ->setHelp(t('label.videos-help', [], 'book'))
                ->hideOnIndex()
                ->setEntryType(BookVideoType::class),
            CollectionField::new('watchLinks')
                ->setLabel(t('label.links', [], 'book'))
                ->setHelp(t('label.watch_links-help', [], 'book'))
                ->hideOnIndex()
                ->setEntryType(BookLinkType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false),
            // "Buy" - the bookshops and the site's own shop, after the crowdfunding as on the page: a book is helped out before the one already out is bought
            FormField::addFieldset(t('label.buy', [], 'book'))
                ->hideOnIndex(),
            CollectionField::new('buyLinks')
                ->setLabel(false)
                ->setHelp(t('label.buy_links-help', [], 'book'))
                ->hideOnIndex()
                ->setEntryType(BookLinkType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false),
            // The safety net: a platform kind a site declared itself belongs to none of the three gestures above, and its row would open nowhere (see Book::getOtherLinks()). The fieldset is only written when such a row exists
            ...($book?->getOtherLinks()->isEmpty() ?? true ? [] : [
                FormField::addFieldset(t('label.links', [], 'book'))
                    ->hideOnIndex(),
                CollectionField::new('otherLinks')
                    ->setLabel(false)
                    ->setHelp(t('label.other_links-help', [], 'book'))
                    ->hideOnIndex()
                    ->setEntryType(BookLinkType::class)
                    ->allowAdd()
                    ->allowDelete()
                    ->setFormTypeOption('by_reference', false),
            ]),
            // Editions - what the book comes out under and nothing more: the ISBN, the size, the pagination, the date. Its files and its platforms belong to the book, under the gesture they serve (see BookEditionType). Another version of the text is no edition: it is a book apart, named above by "Replaced by"
            FormField::addFieldset(t('label.editions', [], 'book'))
                ->hideOnIndex(),
            // The marker laid on the row is what BookGuidedProjectProvider's tour points at, a collection printing no field id of its own. Labelled by the fieldset it is alone in, which would otherwise print the same word twice
            CollectionField::new('editions')
                ->setLabel(false)
                ->setHelp(t('label.editions-help', [], 'book'))
                ->hideOnIndex()
                ->setEntryType(BookEditionType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                ->setFormTypeOption('row_attr', ['data-book-editions' => '1']),
            // Presse management
            FormField::addFieldset(t('label.presse', [], 'book'))
                ->hideOnIndex(),
            CollectionField::new('presses')
                ->setLabel(false)
                ->hideOnIndex()
                ->setEntryType(BookPresseType::class),
            // Marketing management
            FormField::addFieldset(t('label.marketing', [], 'book'))
                ->hideOnIndex(),
            CollectionField::new('marketings')
                ->setLabel(false)
                ->hideOnIndex()
                ->setEntryType(BookMarketingType::class),

            // Blocks. Crowdfunding
            FormField::addFieldset(t('label.crowdfunding', [], 'book'))
                ->hideOnIndex(),
            // The address the campaign is pledged on, which is what Book:Crowdfunding turns into its "support" button - a plain text field here would let a button be built on something that is not a link
            UrlField::new('crowdfunding')
                ->setLabel(t('label.crowdfunding', [], 'book'))
                ->hideOnIndex(),
            DateField::new('crowdfundingEndDate')
                ->setLabel(t('label.crowdfunding_end_date', [], 'book'))
                ->hideOnIndex(),
        ];
    }

    // The "blocks" tab: what an admin composes the rest of the page with
    private function blockFields(mixed $entity): array
    {
        return [
            FormField::addTab(t('label.blocks', [], 'book'))
                ->hideOnIndex(),
            CollectionField::new('blocks')
                ->setLabel(false)
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

    // Is an image slot still free? Read on the row as it stands in database: the "Add" link comes back on saving a removal, not on the click of the trash - laying another file on the existing row stays the gesture replacing an image
    /** @param Collection<int, mixed>|null $medias */
    private static function isEmpty(?Collection $medias): bool
    {
        return null === $medias || $medias->isEmpty();
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

    // The books a version field offers: neither those of the trash nor the one being edited - choosing oneself closes the loop and drops the book out of the catalog with no version link shown at all
    private function versionQueryBuilder(): callable
    {
        $book = $this->getContext()?->getEntity()?->getInstance();
        $id = $book instanceof Book ? $book->getId() : null;

        return function ($repository) use ($id) {
            $queryBuilder = $repository->createQueryBuilder('v')
                ->andWhere('v.isDeleted = false')
                ->orderBy('v.title', 'ASC');

            // Nothing to exclude on the creation screen: the book has no identity yet
            if (null !== $id) {
                $queryBuilder
                    ->andWhere('v.id != :self')
                    ->setParameter('self', $id);
            }

            return $queryBuilder;
        };
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            // Named in the editor's own language: with no label, EasyAdmin falls back on the class name and prints "Product", "Créer Product"
            ->setEntityLabelInSingular(t('label.book', [], 'book'))
            ->setEntityLabelInPlural(t('label.books', [], 'book'))
            // The volume number is what a catalog is read by, the latest published first - a book carrying none (a standalone title) sorts last
            ->setDefaultSort(['number' => 'DESC'])
            ->addFormTheme('@c975LBook/management/book_crud_form_theme.html.twig')
            ->overrideTemplate('crud/index', '@c975LBook/management/book_crud_index.html.twig')
            ->overrideTemplate('crud/edit', '@c975LBook/management/book_crud_edit.html.twig')
            ->overrideTemplate('crud/new', '@c975LBook/management/book_crud_new.html.twig')
            ->setEntityPermission($this->configService->get('site-role-editor'))
        ;
    }

    // The versions gesture beside the copy one, and they are not the same: duplicating makes two identical, independent books, publishing a new version sets aside what came out so far and leaves the book - its address, its ratings, its translations - free to take the new text
    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        $publishVersion = Action::new('publishVersion', t('action.publish_new_version', [], 'book'), 'fa fa-code-branch')
            ->linkToUrl(fn (Book $book): string => $this->publishVersionUrl($book))
            // A book already replaced is not replaced a second time: the version replacing it is what comes in versions in its turn, so a third version chains onto the second
            ->displayIf(static fn (Book $book): bool => !$book->isDeleted() && !$book->getNewerVersion() instanceof Book)
            ->askConfirmation(t('confirm.publish_new_version', [], 'book'))
            ->addCssClass('btn btn-secondary');

        // The same button, greyed, for the books that cannot come in versions any more: removing it would shift the row of icons from one line to the next, and a moving column reads worse than a grey button
        $publishVersionDone = Action::new('publishVersionDone', t('action.publish_new_version', [], 'book'), 'fa fa-code-branch')
            ->linkToUrl(static fn (Book $book): string => '#')
            ->displayIf(static fn (Book $book): bool => !$book->isDeleted() && $book->getNewerVersion() instanceof Book)
            ->setHtmlAttributes(['aria-disabled' => 'true', 'tabindex' => '-1'])
            ->addCssClass('btn btn-secondary disabled');

        $disabledTitle = $this->translator->trans('action.publish_new_version_disabled', [], 'book');

        // Added before the trait configures: it is what lays out the index row, and reorder() refuses an action it does not know yet (see extraIndexActions())
        $actions = $actions
            ->add(Crud::PAGE_INDEX, $publishVersion)
            ->add(Crud::PAGE_EDIT, $publishVersion)
            ->add(Crud::PAGE_INDEX, $publishVersionDone)
            ->add(Crud::PAGE_EDIT, $publishVersionDone)
        ;

        return $this->trashableActions($actions)
            ->update(Crud::PAGE_INDEX, 'publishVersion', fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.publish_new_version', [], 'book'),
            ))
            ->update(Crud::PAGE_INDEX, 'publishVersionDone', fn (Action $action) => EasyAdminActionHelper::toIconOnly($action, $disabledTitle))
            ->update(Crud::PAGE_EDIT, 'publishVersionDone', fn (Action $action) => $action->setHtmlAttributes([
                'aria-disabled' => 'true',
                'tabindex' => '-1',
                'title' => $disabledTitle,
            ]))
            ->setPermission('publishVersion', $this->configService->get('site-role-editor'))
            ->setPermission('publishVersionDone', $this->configService->get('site-role-editor'))
        ;
    }

    // The two faces of the versions gesture keep their column between the copy and the trash - only one of the two shows per row. No #[\Override]: the redefined method comes from a trait, which PHP considers declared in this very class
    protected function extraIndexActions(): array
    {
        return ['publishVersion', 'publishVersionDone'];
    }

    // Sets aside what the book published so far: a twin book is born, takes the released editions with their files and their platforms, and names this one as the version replacing it. The book itself does not move address - it is the one outside links carry and it must lead to the most recent text - and starts again with editions to fill (see BookVersionPublisher)
    #[AdminRoute('/{entityId}/publish-version')]
    public function publishVersion(AdminContext $context, EntityManagerInterface $entityManager, Request $request): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        if (!$this->isCsrfTokenValid(self::PUBLISH_VERSION_CSRF_TOKEN, $request->query->getString('token'))) {
            return $this->redirect($this->bookIndexUrl());
        }

        $book = $context->getEntity()->getInstance();

        if (!$book instanceof Book || $book->getNewerVersion() instanceof Book) {
            $this->addFlash('warning', $this->translator->trans('flash.version_already_published', [], 'book'));

            return $this->redirect($this->bookIndexUrl());
        }

        // The version already set aside, read before the twin is born: the twin takes its place beside the book, and it hangs behind the twin - a third version chains onto the second (see Book::$newerVersion)
        $chained = $book->getPreviousVersion();

        // Freed first, and written: the link is unique in database, and the twin's row cannot name the book while the old one still names it
        if ($chained instanceof Book) {
            $chained->setNewerVersion(null);
            $entityManager->flush();
        }

        $title = (string) $book->getTitle() . $this->translator->trans('label.previous_version_suffix', [], 'book');
        $previous = $this->versionPublisher->createPreviousVersion($book, $title);

        // Saved before any move: an edition can only name a book the database knows (see BookVersionPublisher::moveEdition())
        $entityManager->persist($previous);
        $entityManager->flush();

        // The chain picked up from below: the previous version is now replaced by the twin, itself replaced by the book
        $chained?->setNewerVersion($previous);

        // Read first, moved after: moveEdition() detaches each edition from the book, and the collection would change under the loop
        foreach ($book->getEditions()->toArray() as $edition) {
            $this->versionPublisher->moveEdition($edition, $previous);
        }

        // And the pages of the text as it came out, which go with it: the one staying gets its own (see BookVersionPublisher::moveMedias())
        $this->versionPublisher->moveMedias($book, $previous, ['extract', 'flipbook']);

        $entityManager->flush();

        $this->addFlash('success', $this->translator->trans('flash.version_published', ['%title%' => $title], 'book'));

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::EDIT)
                ->setEntityId($book->getId())
                ->generateUrl()
        );
    }

    // The button's address, its csrf token in the request: the action is a GET, which an image laid on a third-party page would otherwise fire for a logged-in editor
    private function publishVersionUrl(Book $book): string
    {
        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction('publishVersion')
            ->setEntityId($book->getId())
            ->set('token', $this->csrfTokenManager->getToken(self::PUBLISH_VERSION_CSRF_TOKEN)->getValue())
            ->generateUrl();
    }

    // The catalog, where a refused action comes back
    private function bookIndexUrl(): string
    {
        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl();
    }

    protected function duplicateEntity(mixed $entity): object
    {
        return $this->duplicator->duplicateBook($entity);
    }

    protected function serializeSelection(array $ids): array
    {
        return $this->bookExportProvider->serializeIds($ids);
    }
}
