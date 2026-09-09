<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Controller\Management;

use c975L\BookBundle\Entity\Character;
use c975L\BookBundle\Form\CharacterMediaType;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Form\TrixEditorType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

use function Symfony\Component\Translation\t;

// A screen of their own and not a row inside their serie's: a character carries a face, a presentation and a slug the urls are built on, which is more than a form nested in another can hold - and it is what gives each of them an address the page's pencil can lead to (see BookEditUrlExtension::characterEditUrl())
// No TrashableCrudTrait: a character is deleted outright, its planches simply losing the link - so there is nothing to restore, and the serie's own export carries them (see SerieExportProvider::exportSerieData())
class CharacterCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Character::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(t('label.character', [], 'book'))
            ->setEntityLabelInPlural(t('label.characters', [], 'book'))
            ->setDefaultSort(['serie' => 'ASC', 'position' => 'ASC'])
            ->setSearchFields(['name', 'slug', 'presentation'])
            ->overrideTemplate('crud/index', '@c975LBook/management/character_crud_index.html.twig')
            ->overrideTemplate('crud/edit', '@c975LBook/management/character_crud_edit.html.twig')
            ->overrideTemplate('crud/new', '@c975LBook/management/character_crud_new.html.twig')
            ->setEntityPermission($this->configService->get('site-role-editor'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IntegerField::new('id')
                ->setFormTypeOption('disabled', 'disabled')
                ->hideOnForm(),
            AssociationField::new('serie')
                ->setLabel(t('label.serie', [], 'book')),
            TextField::new('name')
                ->setLabel(t('label.character', [], 'book')),
            // The order the cards are presented in, laid by dragging them on the index (see UiBundle's assets/js/ea-index-sort.js)
            IntegerField::new('position')
                ->setLabel(t('label.position', [], 'book'))
                ->setFormTypeOption('attr', ['class' => 'ui-sort-position']),
            SlugField::new('slug')
                ->hideOnIndex()
                ->setLabel(t('label.slug', [], 'book'))
                ->setHelp(t('label.character_slug-help', [], 'book'))
                ->setTargetFieldName('name'),
            // What parts one serie's people into more than one row of cards - left empty, they are presented as one
            TextField::new('groupName')
                ->hideOnIndex()
                ->setLabel(t('label.character_group', [], 'book')),
            // TrixEditorType rather than EasyAdmin's own editor: its widget is where the rephrase button is wired
            TextareaField::new('presentation')
                ->hideOnIndex()
                ->setLabel(t('label.presentation', [], 'book'))
                ->setFormType(TrixEditorType::class),

            FormField::addFieldset(t('label.media', [], 'book'))
                ->hideOnIndex(),
            CollectionField::new('medias')
                ->setLabel(t('label.media', [], 'book'))
                ->hideOnIndex()
                ->setEntryType(CharacterMediaType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                ->setFormTypeOption('row_attr', ['data-character-portraits' => '1']),
        ];
    }
}
