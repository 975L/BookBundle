<?php

namespace c975L\BookBundle\Controller\Management;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookEdition;
use c975L\BookBundle\Form\BookLinkType;
use c975L\BookBundle\Form\BookMediaType;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use c975L\ConfigBundle\Management\EasyAdminActionHelper;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

// One published version of a book - the paperback, the ebook, the audiobook - edited on a screen of its own, holding its ISBN, its release date and, above all, its own files and its own platforms. A book used to carry all of them in one flat list, where the pages of the illustrated version sat next to those of the original and each row had to name the version it belonged to; here the version is the screen, so nothing has to be told apart by hand. Scoped to one book through ?book=<id>, the same way CollectionItemCrudController is scoped to its collection
class BookEditionCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly BookCustomizationRegistry $customizationRegistry,
        private readonly BookRepository $bookRepository,
        private readonly ConfigServiceInterface $configService,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return BookEdition::class;
    }

    // A version only makes sense inside the book it is one of - without a resolvable ?book=<id>, bounces back to the catalog rather than listing every version of every book
    #[\Override]
    public function index(AdminContext $context): KeyValueStore | Response
    {
        if (null === $this->currentBook()) {
            return $this->redirectToBooksList();
        }

        return parent::index($context);
    }

    // Same guard as index() - reachable directly (a stale bookmark) without ever having browsed into a book first
    #[\Override]
    public function new(AdminContext $context): KeyValueStore | Response
    {
        if (null === $this->currentBook()) {
            return $this->redirectToBooksList();
        }

        return parent::new($context);
    }

    #[\Override]
    public function createIndexQueryBuilder(...$args): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder(...$args)
            ->addOrderBy('entity.position', 'ASC')
        ;

        $book = $this->currentBook();
        if (null !== $book) {
            $queryBuilder->andWhere('entity.book = :book')->setParameter('book', $book);
        }

        return $queryBuilder;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        return [
            ChoiceField::new('kind')
                ->setLabel(t('label.edition_kind', [], 'book'))
                ->setChoices(array_flip($this->customizationRegistry->getEditionKinds())),
            TextField::new('isbn')
                ->setLabel(t('label.isbn', [], 'book')),
            // Left empty for an ISBN reserved ahead of the version it names, which reads as "à paraître" rather than as a missing date
            DateField::new('published')
                ->setLabel(t('label.published', [], 'book'))
                ->setHelp(t('label.edition_published-help', [], 'book')),
            IntegerField::new('pages')
                ->setLabel(t('label.pages', [], 'book'))
                ->setHelp(t('label.edition_pages-help', [], 'book')),
            IntegerField::new('position')
                ->setLabel(t('label.position', [], 'book'))
                ->setFormTypeOption('attr', ['class' => 'ui-sort-position']),

            // The files of this version alone - no field asks which version they belong to, the screen already says it
            FormField::addFieldset(t('label.media', [], 'book'))
                ->onlyOnForms(),
            CollectionField::new('medias')
                ->setLabel(t('label.media', [], 'book'))
                ->setHelp(t('label.edition_medias-help', [], 'book'))
                ->onlyOnForms()
                ->setEntryType(BookMediaType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false),

            // Where this version is sold, read or listened to
            FormField::addFieldset(t('label.links', [], 'book'))
                ->onlyOnForms(),
            CollectionField::new('links')
                ->setLabel(t('label.links', [], 'book'))
                ->setHelp(t('label.edition_links-help', [], 'book'))
                ->onlyOnForms()
                ->setEntryType(BookLinkType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false),
        ];
    }

    // New version - the book it belongs to always comes from the browsed context, never from the form. Must happen here rather than in persistEntity(): the files added on the same screen are stored under a path built on the book's slug (see BookMedia::getVichMediaPath()), which the version has to know before the form is even validated
    #[\Override]
    public function createEntity(string $entityFqcn): BookEdition
    {
        $edition = new BookEdition();
        $edition->setBook($this->currentBook());

        return $edition;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(t('label.edition', [], 'book'))
            ->setEntityLabelInPlural(t('label.editions', [], 'book'))
            ->setEntityPermission($this->configService->get('site-role-admin'))
            ->setDefaultSort(['position' => 'ASC'])
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/edit', '@c975LBook/management/book_edition_crud_edit.html.twig')
            ->overrideTemplate('crud/new', '@c975LBook/management/book_edition_crud_new.html.twig')
        ;
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        $role = $this->configService->get('site-role-admin');

        // Back to the book these versions are of, rather than to the catalog: the versions are edited while editing the book
        $backToBookAction = Action::new('book', t('label.book', [], 'book'), 'fas fa-book')
            ->linkToUrl(fn (): string => $this->bookEditUrl())
            ->createAsGlobalAction()
        ;

        // Lets the admin back out of a create/edit without saving - mirrors EasyAdmin's own built-in actions
        $cancelAction = Action::new('cancel', $this->translator->trans('action.cancel', [], 'EasyAdminBundle'), 'fa fa-times')
            ->linkToCrudAction(Action::INDEX)
            ->addCssClass('btn btn-secondary');

        return $actions
            ->add(Crud::PAGE_INDEX, $backToBookAction)
            ->add(Crud::PAGE_NEW, $cancelAction)
            ->add(Crud::PAGE_EDIT, $cancelAction)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.edit', [], 'EasyAdminBundle'),
            ))
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.delete', [], 'EasyAdminBundle'),
            ))
            ->setPermission(Action::INDEX, $role)
            ->setPermission('book', $role)
            ->setPermission(Action::NEW, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            // Detail adds no information beyond what edit already shows
            ->disable(Action::DETAIL)
        ;
    }

    // The book being browsed, read off the query string the book's own screen links with (see BookEditionsField)
    private function currentBook(): ?Book
    {
        $id = $this->requestStack->getCurrentRequest()?->query->get('book');

        return is_numeric($id) ? $this->bookRepository->find((int) $id) : null;
    }

    private function bookEditUrl(): string
    {
        $book = $this->currentBook();

        if (null === $book) {
            return $this->booksListUrl();
        }

        return $this->adminUrlGenerator
            ->setController(BookCrudController::class)
            ->setAction(Action::EDIT)
            ->setEntityId($book->getId())
            ->unset('book')
            ->generateUrl();
    }

    private function booksListUrl(): string
    {
        return $this->adminUrlGenerator
            ->setController(BookCrudController::class)
            ->setAction(Action::INDEX)
            ->unset('book')
            ->generateUrl();
    }

    private function redirectToBooksList(): Response
    {
        return $this->redirect($this->booksListUrl());
    }
}
