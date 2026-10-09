<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Controller\Management;

use c975L\BookBundle\Controller\Management\Trait\ContentLocaleCrudTrait;
use c975L\BookBundle\Entity\BookSettings;
use c975L\BookBundle\Management\BookBlockOwnerResolver;
use c975L\BookBundle\Repository\BookSettingsRepository;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Form\BlockType;
use c975L\UiBundle\Service\BlockFocusUrl;
use c975L\UiBundle\Service\BlockMoveRowAttrBuilder;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

// The catalog's index composed in the back office: one screen editing one row, which holds the blocks the index prints above its listing. There is nothing to list, so the index action never shows a table - it opens that row, creating it the first time anyone comes here (see ShopBundle's ShopSettingsCrudController, which the shop's index is composed with)
class BookSettingsCrudController extends AbstractCrudController
{
    use ContentLocaleCrudTrait;

    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly BlockMoveRowAttrBuilder $blockMoveRowAttrBuilder,
        private readonly BookPublicUrlResolver $publicUrlResolver,
        private readonly BookSettingsRepository $bookSettingsRepository,
        private readonly ConfigServiceInterface $configService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return BookSettings::class;
    }

    // Straight to the single row rather than to a table of one line, the row being created on the first visit - what makes this screen behave as a settings page and not as a catalog
    public function index(AdminContext $context): KeyValueStore | Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        $settings = $this->bookSettingsRepository->findSingle();

        if (null === $settings) {
            $settings = new BookSettings();
            $this->entityManager->persist($settings);
            $this->entityManager->flush();
        }

        return $this->redirect(BlockFocusUrl::build($this->adminUrlGenerator, self::class, $settings->getId()));
    }

    // The catalog's own line, then its blocks
    public function configureFields(string $pageName): iterable
    {
        // The very same edit screen, opened on another language: the catalog's own line as that language says it, the blocks below being translated on their own screens (see ContentLocaleScreen)
        $contentLocale = Crud::PAGE_EDIT === $pageName ? $this->contentLocale() : null;
        if (null !== $contentLocale) {
            return $this->translationFields($contentLocale);
        }

        $entity = $this->adminContextProvider()->getContext()?->getEntity()?->getInstance();

        return [
            // The one line the index prints above everything else, plain text and not a block: it is the catalog's own sentence, and a site that never comes here keeps the one the index has always printed
            FormField::addFieldset(t('label.intro', [], 'book')),
            TextareaField::new('intro')
                ->setLabel(t('label.intro', [], 'book'))
                ->setHelp(t('label.intro-help', [], 'book'))
                ->setNumOfRows(2)
                ->setColumns('col-12')
                ->setRequired(false)
                // Opt-in marker read by the block form theme, which is what puts Donovan under a plain textarea
                ->setFormTypeOption('attr', ['data-ai-rephrase' => true]),

            // Blocks: what the catalog's index says above its listing, composed with UiBundle's kinds - the same collection a book, a serie and a category page hold
            FormField::addFieldset(t('label.blocks', [], 'book')),
            CollectionField::new('blocks')
                ->setLabel(t('label.blocks', [], 'book'))
                ->setHelp(t('label.books_index_blocks-help', [], 'book'))
                // Same reasoning as SerieCrudController: CollectionField's "col-md-8 col-xxl-7" default leaves a nested block editor working in 7/12 of the row
                ->setColumns('col-12')
                ->setEntryType(BlockType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                // The sortable's own attributes, plus the one the guided project points at when it walks the user down to the blocks: the entries are numbered by the collection, so no id inside is stable enough to name (see BookGuidedProjectProvider)
                ->setFormTypeOption('row_attr', ['data-book-settings-blocks' => '1', ...$this->blockMoveRowAttrBuilder->build(BookBlockOwnerResolver::TYPE_SETTINGS, $entity instanceof BookSettings ? $entity->getId() : null)]),
        ];
    }

    // Edit and the link to the page, nothing else: a single row, created by index() and never listed
    public function configureActions(Actions $actions): Actions
    {
        $role = $this->configService->get('site-role-editor');

        // Opens the catalog's index on the site, in a new tab - the page these blocks are composed for, and no button at all on a site serving its books elsewhere (see BookPublicUrlResolver::resolvePath)
        $viewOnSiteAction = Action::new('viewOnSite', t('action.view_on_site', [], 'book'), 'fa fa-external-link-alt')
            ->linkToUrl(fn (): string => (string) $this->publicUrlResolver->resolvePath('book_index'))
            ->setHtmlAttributes(['target' => '_blank'])
            ->displayIf(fn (): bool => null !== $this->publicUrlResolver->resolvePath('book_index'))
            ->addCssClass('btn btn-secondary')
        ;

        return $actions
            ->add(Crud::PAGE_EDIT, $viewOnSiteAction)
            ->setPermission(Action::EDIT, $role)
            ->setPermission('viewOnSite', $role)
            // Nothing to add, nothing to delete, and no detail beyond what edit already shows
            ->disable(Action::NEW, Action::DELETE, Action::DETAIL, Action::BATCH_DELETE)
        ;
    }

    // Named in the editor's own language, and opened on the screen the user actually reads
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(t('label.books_index', [], 'book'))
            ->setEntityLabelInPlural(t('label.books_index', [], 'book'))
            ->setPageTitle(Crud::PAGE_EDIT, t('label.books_index', [], 'book'))
            // The edit page and not the index one: index() redirects straight to the single row, so the screen the user actually reads is the form
            ->overrideTemplate('crud/edit', '@c975LBook/management/book_settings_crud_edit.html.twig')
            ->setEntityPermission($this->configService->get('site-role-editor'))
        ;
    }
}
