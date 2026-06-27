<?php

namespace c975L\BookBundle\Controller\Management;

use c975L\BookBundle\Controller\Management\SerieCrudController;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Form\BookMarketingType;
use c975L\BookBundle\Form\BookMediaType;
use c975L\BookBundle\Form\BookPresseType;
use c975L\BookBundle\Form\BookVideoType;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Form\BlockType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class BookCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Book::class;
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addJsFile('@c975l/ui-bundle/js/blocks.js');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            // Informations
            FormField::addTab('label.informations')
                ->hideOnIndex(),
            TextField::new('title')
                ->setLabel('label.title'),
            SlugField::new('slug')
                ->setLabel('label.slug')
                ->hideOnIndex()
                ->setTargetFieldName('title'),
            TextEditorField::new('summary')
                ->hideOnIndex()
                ->setLabel('label.summary'),
            DateField::new('published')
                ->setLabel('label.published'),
            AssociationField::new('serie')
                ->setLabel('label.serie')
                ->setFormTypeOptions([
                    'by_reference' => true,
                ])
                ->setCrudController(SerieCrudController::class)
                ->autocomplete(),
            ChoiceField::new('language')
                ->setLabel('label.language')
                ->setChoices([
                    'label.french' => 'fr',
                    'label.english' => 'en',
                ]),
            AssociationField::new('translationBook')
                ->setLabel('label.translations')
                ->setFormTypeOption('query_builder', function ($repository) {
                    return $repository->createQueryBuilder('s')
                        ->orderBy('s.title', 'ASC');
                })
                ->formatValue(function ($value, $entity) {
                    if (!$value) return null;
                    return sprintf('%s (%s)', $value->getTitle(), $value->getLanguage());
                }),
            TextField::new('format')
                ->setLabel('label.format'),
            NumberField::new('pages')
                ->setLabel('label.pages'),
            // Author
            FormField::addFieldset('label.author')
                ->hideOnIndex(),
            TextField::new('author')
                ->setLabel('label.author'),
            UrlField::new('authorWebsite')
                ->setLabel('label.author_website'),

            // Illustrator
            FormField::addFieldset('label.illustrator')
                ->hideOnIndex(),
            TextField::new('illustrator')
                ->setLabel('label.illustrator'),
            UrlField::new('illustratorWebsite')
                    ->setLabel('label.illustrator_website'),
            // Dates
            DateTimeField::new('creation')
                ->setLabel('label.creation')
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),
            DateTimeField::new('modification')
                ->setLabel('label.modification')
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),

            // Technical informations
            FormField::addTab('label.technical_informations')
                ->hideOnIndex(),
            // ISBN
            FormField::addFieldset('label.isbn')
                ->hideOnIndex(),
            NumberField::new('isbnPaper')
                ->setLabel('label.isbn_paper')
                ->hideOnIndex(),
            NumberField::new('isbnDigital')
                ->setLabel('label.isbn_digital')
                ->hideOnIndex(),
            // EPUB
            FormField::addFieldset('label.epub')
                ->hideOnIndex(),
            TextField::new('epub_gplay')
                ->setLabel('label.epub_gplay')
                ->setHelp('label.epub_gplay-help')
                ->hideOnIndex(),
            TextField::new('epub_fnac')
                ->setLabel('label.epub_fnac')
                ->setHelp('label.epub_fnac-help')
                ->hideOnIndex(),
            TextField::new('epub_kobo')
                ->setLabel('label.epub_kobo')
                ->setHelp('label.epub_kobo-help')
                ->hideOnIndex(),
            TextField::new('epub_apple')
                ->setLabel('label.epub_apple')
                ->setHelp('label.epub_apple-help')
                ->hideOnIndex(),
            // Crowdfunding
            FormField::addFieldset('label.crowdfunding')
                ->hideOnIndex(),
            TextField::new('crowdfunding')
                ->setLabel('label.crowdfunding')
                ->hideOnIndex(),
            DateField::new('crowdfundingEndDate')
                ->setLabel('label.crowdfunding_end_date')
                ->hideOnIndex(),

            // Media
            FormField::addTab('label.media')
                ->hideOnIndex(),
            CollectionField::new('medias')
                ->hideOnIndex()
                ->setEntryType(BookMediaType::class),
            // Videos
            FormField::addFieldset('label.videos')
                ->hideOnIndex(),
            CollectionField::new('videos')
                ->hideOnIndex()
                ->setEntryType(BookVideoType::class),
            // Marketing management
            FormField::addFieldset('label.marketing')
                ->hideOnIndex(),
            CollectionField::new('marketings')
                ->hideOnIndex()
                ->setEntryType(BookMarketingType::class),
            // Presse management
            FormField::addFieldset('label.presse')
                ->hideOnIndex(),
            CollectionField::new('presses')
                ->hideOnIndex()
                ->setEntryType(BookPresseType::class),

            // Blocks
            FormField::addTab('label.blocks')
                ->hideOnIndex(),
            CollectionField::new('blocks')
                ->hideOnIndex()
                ->setEntryType(BlockType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false),
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
        $role = $this->configService->get('site-role-needed');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::INDEX, $role)
            ->setPermission(Action::NEW, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            ->setPermission(Action::DETAIL, $role)
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityPermission($this->configService->get('site-role-needed'))
            ->setFormOptions(['translation_domain' => 'book'])
        ;
    }
}
