<?php

namespace c975L\BookBundle\Controller\Management;

use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Form\SerieMediaType;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Form\BlockType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class SerieCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Serie::class;
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addJsFile('@c975l/ui-bundle/js/blocks.js');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IntegerField::new('id')
                ->setFormTypeOption('disabled', 'disabled'),
            TextField::new('title')
                ->setLabel('label.title'),
            SlugField::new('slug')
                ->hideOnIndex()
                ->setTargetFieldName('title'),
            TextEditorField::new('summary')
                ->hideOnIndex()
                ->setLabel('label.summary'),
            ChoiceField::new('language')
                ->setLabel('label.language')
                ->setChoices([
                    'label.french' => 'fr',
                    'label.english' => 'en',
                ]),

            // Media management
            FormField::addFieldset('label.cover')
                ->hideOnIndex(),
            CollectionField::new('covers')
                ->setLabel('label.cover')
                ->setHelp('label.cover-help')
                ->hideOnIndex()
                ->setEntryType(SerieMediaType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false),

            FormField::addFieldset('label.logo')
                ->hideOnIndex(),
            CollectionField::new('logos')
                ->setLabel('label.logo')
                ->setHelp('label.logo-help')
                ->hideOnIndex()
                ->setEntryType(SerieMediaType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false),

            // Blocks
            FormField::addFieldset('label.blocks')
                ->hideOnIndex(),
            CollectionField::new('blocks')
                ->setLabel('label.blocks')
                ->setHelp('label.blocks-help')
                ->hideOnIndex()
                ->setEntryType(BlockType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false),

            // Dates
            DateTimeField::new('creation')
                ->setLabel('label.creation')
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),
            DateTimeField::new('modification')
                ->setLabel('label.modification')
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, mixed $serie): void
    {
        $serie->setCreation(new \DateTime());
        $serie->setModification(new \DateTime());
        $serie->setUser($this->getUser());

        parent::persistEntity($entityManager, $serie);
    }

    public function updateEntity(EntityManagerInterface $entityManager, mixed $serie): void
    {
        $serie->setModification(new \DateTime());
        $serie->setUser($this->getUser());

        parent::updateEntity($entityManager, $serie);
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
