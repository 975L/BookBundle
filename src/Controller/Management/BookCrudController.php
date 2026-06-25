<?php

namespace c975L\BookBundle\Controller\Management;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Form\BookMediaType;
use c975L\BookBundle\Form\BookVideoType;
use c975L\BookBundle\Form\BookPresseType;
use c975L\BookBundle\Form\BookMarketingType;
use c975L\BookBundle\Controller\Management\SerieCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

#[IsGranted('ROLE_ADMIN')]
class BookCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Book::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IntegerField::new('id')
                ->setFormTypeOption('disabled', 'disabled'),
            TextField::new('title')
                ->setLabel('Titre'),
            SlugField::new('slug')
                ->hideOnIndex()
                ->setTargetFieldName('title'),
            AssociationField::new('serie')
                ->setLabel('Série')
                ->setFormTypeOptions([
                    'by_reference' => true,
                ])
                ->setCrudController(SerieCrudController::class)
                ->autocomplete(),
            TextField::new('age')
                ->setLabel('Âge'),
            TextField::new('author')
                ->setLabel('Auteur'),
            UrlField::new('authorWebsite')
                ->setLabel('Site web auteur'),
            TextField::new('illustrator')
                ->setLabel('Illustrateur'),
            TextareaField::new('summary')
                ->hideOnIndex()
                ->setLabel('Résumé'),
            ChoiceField::new('language')
                ->setLabel('Langue')
                ->setChoices([
                    'Français' => 'fr',
                    'English' => 'en',
                ]),
            AssociationField::new('translationBook')
                ->setLabel('Traductions')
                ->setFormTypeOption('query_builder', function ($repository) {
                    return $repository->createQueryBuilder('s')
                        ->orderBy('s.title', 'ASC');
                })
                ->formatValue(function ($value, $entity) {
                    if (!$value) return null;
                    return sprintf('%s (%s)', $value->getTitle(), $value->getLanguage());
                }),
            TextField::new('format')
                ->setLabel('Format'),
            NumberField::new('pages')
                ->setLabel('Pages'),
            NumberField::new('isbn')
                ->hideOnIndex()
                ->setLabel('ISBN'),
            NumberField::new('isbnDigital')
                ->hideOnIndex()
                ->setLabel('ISBN Numérique'),
            TextField::new('crowdfunding')
                ->hideOnIndex()
                ->setLabel('Financement'),
            DateField::new('crowdfundingEndDate')
                ->hideOnIndex()
                ->setLabel('Date de fin'),
            TextField::new('epub_gplay')
                ->hideOnIndex(),
            TextField::new('epub_fnac')
                ->hideOnIndex(),
            TextField::new('epub_kobo')
                ->hideOnIndex(),
            TextField::new('epub_apple')
                ->hideOnIndex(),
            DateField::new('published')
                ->setLabel('Publiée'),

            // Media management
            FormField::addFieldset('Media')
                ->hideOnIndex(),
            CollectionField::new('medias')
                ->hideOnIndex()
                ->setEntryType(BookMediaType::class),

            // Videos
            FormField::addFieldset('Vidéos')
                ->hideOnIndex(),
            CollectionField::new('videos')
                ->hideOnIndex()
                ->setEntryType(BookVideoType::class),

            // Marketing management
            FormField::addFieldset('Marketing')
                ->hideOnIndex(),
            CollectionField::new('marketings')
                ->hideOnIndex()
                ->setEntryType(BookMarketingType::class),

            // Presse management
            FormField::addFieldset('Presse')
                ->hideOnIndex(),
            CollectionField::new('presses')
                ->hideOnIndex()
                ->setEntryType(BookPresseType::class),

            // Dates
            DateTimeField::new('creation')
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),
            DateTimeField::new('modification')
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::NEW, 'ROLE_ADMIN')
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityPermission('ROLE_ADMIN')
        ;
    }
}
