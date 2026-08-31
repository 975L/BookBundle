<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Management;

use c975L\BookBundle\Controller\Management\BookCrudController;
use c975L\BookBundle\Controller\Management\ContributorCrudController;
use c975L\BookBundle\Controller\Management\SerieCrudController;
use c975L\BookBundle\Controller\Management\StripCrudController;
use c975L\ConfigBundle\Management\GuidedProjectProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

// This bundle's guided projects, running the 6000 block GuidedProjectProviderInterface reserves them - the same docblock stating every other bundle's, so a range is read there rather than recopied here. They follow the order a catalog is actually built in - the people are credited by the series and the books, the serie holds the books, so each comes before what names it. Only the opening step of each carries an url: from there the parcours walks the screen the user has been sent to, highlighting the button or the field they are meant to use next (see ConfigBundle's assets/js/guided-project.js)
// A field is pointed at through the widget the user actually sees: EasyAdmin turns a choice or an association into a TomSelect (the original <select> being clipped away by "ts-hidden-accessible"), and an association calling autocomplete() is rendered by CrudAutocompleteType, which prints its select under an inner field named "autocomplete" - hence the "_autocomplete" suffix those steps carry, an "#Entity_property" on one of them matching nothing at all and TrixEditorType hides its textarea behind "d-none", so an "#Entity_property" step on those would outline something nobody can see. A collection prints no field id at all, and is marked on its own row instead (see the "data-*" markers of the CRUD controllers)
class BookGuidedProjectProvider implements GuidedProjectProviderInterface
{
    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    public function getGuidedProjects(): array
    {
        return [
            $this->contributorCreationProject(),
            $this->serieCreationProject(),
            $this->bookCreationProject(),
            $this->mediaMoveProject(),
            $this->bookCompositionProject(),
            $this->sortingProject(),
            $this->stripCreationProject(),
            $this->duplicationProject(),
            $this->versionPublicationProject(),
            $this->hiddenProject(),
            $this->trashProject(),
            $this->exportProject(),
        ];
    }

    // The people come before everything else: a serie as much as a book credits one by picking them from this list, and creating them the other way round means going back to edit what already names them
    private function contributorCreationProject(): array
    {
        return [
            'slug' => 'book-contributor-creation',
            'label' => 'label.guided_project_book_contributor_creation',
            'description' => 'description.guided_project_book_contributor_creation',
            'translation_domain' => 'book',
            'order' => 6005,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_book_contributor_creation_open',
                    'description' => 'description.guided_step_book_contributor_creation_open',
                    'url' => $this->contributorIndexUrl(),
                ],
                [
                    'label' => 'label.guided_step_book_contributor_creation_new',
                    'description' => 'description.guided_step_book_contributor_creation_new',
                    'highlight' => '.action-new',
                ],
                [
                    'label' => 'label.guided_step_book_contributor_creation_name',
                    'description' => 'description.guided_step_book_contributor_creation_name',
                    'highlight' => '#Contributor_name',
                ],
                [
                    // The Trix editor, the textarea carrying the id being hidden behind it
                    'label' => 'label.guided_step_book_contributor_creation_summary',
                    'description' => 'description.guided_step_book_contributor_creation_summary',
                    'highlight' => 'trix-editor[input="Contributor_summary"]',
                ],
                [
                    'label' => 'label.guided_step_book_contributor_creation_website',
                    'description' => 'description.guided_step_book_contributor_creation_website',
                    'highlight' => '#Contributor_website',
                ],
                [
                    // The marker laid on the row by ContributorCrudController, a collection printing no field id
                    'label' => 'label.guided_step_book_contributor_creation_portrait',
                    'description' => 'description.guided_step_book_contributor_creation_portrait',
                    'highlight' => '[data-contributor-portraits]',
                ],
                [
                    'label' => 'label.guided_step_book_contributor_creation_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_book_contributor_creation_done',
                    'description' => 'description.guided_step_book_contributor_creation_done',
                ],
            ],
        ];
    }

    // The serie comes first: a book names the one it belongs to, and creating it the other way round means going back to edit the book
    private function serieCreationProject(): array
    {
        return [
            'slug' => 'book-serie-creation',
            'label' => 'label.guided_project_book_serie_creation',
            'description' => 'description.guided_project_book_serie_creation',
            'translation_domain' => 'book',
            'order' => 6010,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_book_serie_creation_open',
                    'description' => 'description.guided_step_book_serie_creation_open',
                    'url' => $this->serieIndexUrl(),
                ],
                [
                    'label' => 'label.guided_step_book_serie_creation_new',
                    'description' => 'description.guided_step_book_serie_creation_new',
                    'highlight' => '.action-new',
                ],
                [
                    'label' => 'label.guided_step_book_serie_creation_title',
                    'description' => 'description.guided_step_book_serie_creation_title',
                    'highlight' => '#Serie_title',
                ],
                [
                    // The TomSelect widget standing in for the select, which EasyAdmin clips out of sight
                    'label' => 'label.guided_step_book_serie_creation_kind',
                    'description' => 'description.guided_step_book_serie_creation_kind',
                    'highlight' => '#Serie_kind + .ts-wrapper',
                ],
                [
                    // The Trix editor, the textarea carrying the id being hidden behind it
                    'label' => 'label.guided_step_book_serie_creation_summary',
                    'description' => 'description.guided_step_book_serie_creation_summary',
                    'highlight' => 'trix-editor[input="Serie_summary"]',
                ],
                [
                    // The TomSelect widget of the autocompleted association, whose select CrudAutocompleteType names after its own inner field (see the "_autocomplete" suffix)
                    'label' => 'label.guided_step_book_serie_creation_author',
                    'description' => 'description.guided_step_book_serie_creation_author',
                    'highlight' => '#Serie_author_autocomplete + .ts-wrapper',
                ],
                [
                    'label' => 'label.guided_step_book_serie_creation_covers',
                    'description' => 'description.guided_step_book_serie_creation_covers',
                    'highlight' => '[data-serie-covers]',
                ],
                [
                    'label' => 'label.guided_step_book_serie_creation_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_book_serie_creation_done',
                    'description' => 'description.guided_step_book_serie_creation_done',
                ],
            ],
        ];
    }

    // The book itself, up to the cover that makes its page look like something - the fields spread over tabs, which the parcours opens as it goes
    private function bookCreationProject(): array
    {
        return [
            'slug' => 'book-creation',
            'label' => 'label.guided_project_book_creation',
            'description' => 'description.guided_project_book_creation',
            'translation_domain' => 'book',
            'order' => 6020,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_book_creation_open',
                    'description' => 'description.guided_step_book_creation_open',
                    'url' => $this->bookIndexUrl(),
                ],
                [
                    'label' => 'label.guided_step_book_creation_new',
                    'description' => 'description.guided_step_book_creation_new',
                    'highlight' => '.action-new',
                ],
                [
                    'label' => 'label.guided_step_book_creation_title',
                    'description' => 'description.guided_step_book_creation_title',
                    'highlight' => '#Book_title',
                ],
                [
                    'label' => 'label.guided_step_book_creation_serie',
                    'description' => 'description.guided_step_book_creation_serie',
                    'highlight' => '#Book_serie_autocomplete + .ts-wrapper',
                ],
                [
                    'label' => 'label.guided_step_book_creation_published',
                    'description' => 'description.guided_step_book_creation_published',
                    'highlight' => '#Book_published',
                ],
                [
                    // Same autocompleted association as the serie's, left empty for a book crediting whoever its serie does (see Book::getEffectiveAuthor())
                    'label' => 'label.guided_step_book_creation_author',
                    'description' => 'description.guided_step_book_creation_author',
                    'highlight' => '#Book_author_autocomplete + .ts-wrapper',
                ],
                [
                    // The second tab of the form, an ISBN belonging to an edition and no longer to the book itself (see BookCrudController)
                    'label' => 'label.guided_step_book_creation_technical_tab',
                    'description' => 'description.guided_step_book_creation_technical_tab',
                    'highlight' => '.form-tabs-tablist .nav-item:nth-child(2) .nav-link',
                ],
                [
                    // The editions are edited right here: a collection prints no field id, so the row's own marker is what the tour points at (see BookCrudController)
                    'label' => 'label.guided_step_book_creation_editions',
                    'description' => 'description.guided_step_book_creation_editions',
                    'highlight' => '[data-book-editions]',
                ],
                [
                    // The button unfolding one more edition, its files and its platforms added inside the panel it opens
                    'label' => 'label.guided_step_book_creation_editions_add',
                    'description' => 'description.guided_step_book_creation_editions_add',
                    'highlight' => '[data-book-editions] .field-collection-add-button',
                ],
                [
                    'label' => 'label.guided_step_book_creation_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_book_creation_done',
                    'description' => 'description.guided_step_book_creation_done',
                ],
            ],
        ];
    }

    // A file lands where it was dropped, and moving it afterwards is a drag from one collection to the other rather than a delete and a new upload - the markers carrying that gesture are laid by BookMediaMoveRowAttrBuilder, which lays nothing on a book with no id yet, so the parcours edits an existing book rather than creating one
    private function mediaMoveProject(): array
    {
        return [
            'slug' => 'book-media-move',
            'label' => 'label.guided_project_book_media_move',
            'description' => 'description.guided_project_book_media_move',
            'translation_domain' => 'book',
            'order' => 6025,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_book_media_move_open',
                    'description' => 'description.guided_step_book_media_move_open',
                    'url' => $this->bookIndexUrl(),
                ],
                [
                    // An existing book, the markers of the gesture being laid on its files only once it has an id (see BookMediaMoveRowAttrBuilder::build())
                    'label' => 'label.guided_step_book_media_move_edit',
                    'description' => 'description.guided_step_book_media_move_edit',
                    'highlight' => '.action-edit',
                ],
                [
                    'label' => 'label.guided_step_book_media_move_tab',
                    'description' => 'description.guided_step_book_media_move_tab',
                    'highlight' => '.form-tabs-tablist .nav-item:nth-child(2) .nav-link',
                ],
                [
                    'label' => 'label.guided_step_book_media_move_extracts',
                    'description' => 'description.guided_step_book_media_move_extracts',
                    'highlight' => '[data-ui-move-target="extract"]',
                ],
                [
                    // The grip UiBundle's ea-sortable.js lays on the header bar of each row
                    'label' => 'label.guided_step_book_media_move_handle',
                    'description' => 'description.guided_step_book_media_move_handle',
                    'highlight' => '[data-ui-move-target="extract"] .ui-sort-handle',
                ],
                [
                    'label' => 'label.guided_step_book_media_move_done',
                    'description' => 'description.guided_step_book_media_move_done',
                ],
            ],
        ];
    }

    // What a book's page holds beyond its own fields: the blocks, composed in the back-office with the kinds of UiBundle
    private function bookCompositionProject(): array
    {
        return [
            'slug' => 'book-composition',
            'label' => 'label.guided_project_book_composition',
            'description' => 'description.guided_project_book_composition',
            'translation_domain' => 'book',
            'order' => 6030,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_book_composition_open',
                    'description' => 'description.guided_step_book_composition_open',
                    'url' => $this->bookIndexUrl(),
                ],
                [
                    'label' => 'label.guided_step_book_composition_edit',
                    'description' => 'description.guided_step_book_composition_edit',
                    'highlight' => '.action-edit',
                ],
                [
                    // The last tab of the form, whatever the tabs before it
                    'label' => 'label.guided_step_book_composition_tab',
                    'description' => 'description.guided_step_book_composition_tab',
                    'highlight' => '.form-tabs-tablist .nav-item:last-child .nav-link',
                ],
                [
                    // The sorting group UiBundle's BlockMoveRowAttrBuilder puts on the row, a collection printing no field id
                    'label' => 'label.guided_step_book_composition_add',
                    'description' => 'description.guided_step_book_composition_add',
                    'highlight' => '[data-ui-sort-group]',
                ],
                [
                    'label' => 'label.guided_step_book_composition_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_book_composition_done',
                    'description' => 'description.guided_step_book_composition_done',
                ],
            ],
        ];
    }

    // The order the public pages follow is laid by dragging the rows here, and nothing on the screen says so - only the series and the people are sorted this way, their indexes being the two declaring the reorder markers (see serie_crud_index.html.twig and contributor_crud_index.html.twig)
    private function sortingProject(): array
    {
        return [
            'slug' => 'book-sorting',
            'label' => 'label.guided_project_book_sorting',
            'description' => 'description.guided_project_book_sorting',
            'translation_domain' => 'book',
            'order' => 6035,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_book_sorting_open',
                    'description' => 'description.guided_step_book_sorting_open',
                    'url' => $this->serieIndexUrl(),
                ],
                [
                    // EasyAdmin keys every index cell by the property it prints (see its crud/index.html.twig)
                    'label' => 'label.guided_step_book_sorting_column',
                    'description' => 'description.guided_step_book_sorting_column',
                    'highlight' => 'td[data-column="position"]',
                ],
                [
                    // The grip UiBundle's ea-index-sort.js lays in that cell at mount time
                    'label' => 'label.guided_step_book_sorting_handle',
                    'description' => 'description.guided_step_book_sorting_handle',
                    'highlight' => 'td[data-column="position"] .ui-sort-handle',
                ],
                [
                    'label' => 'label.guided_step_book_sorting_done',
                    'description' => 'description.guided_step_book_sorting_done',
                ],
            ],
        ];
    }

    // A strip is read inside the serie that tells it, and numbered within it: the parcours walks what places it there, then the drawing itself
    private function stripCreationProject(): array
    {
        return [
            'slug' => 'book-strip-creation',
            'label' => 'label.guided_project_book_strip_creation',
            'description' => 'description.guided_project_book_strip_creation',
            'translation_domain' => 'book',
            'order' => 6040,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_book_strip_creation_open',
                    'description' => 'description.guided_step_book_strip_creation_open',
                    'url' => $this->stripIndexUrl(),
                ],
                [
                    'label' => 'label.guided_step_book_strip_creation_new',
                    'description' => 'description.guided_step_book_strip_creation_new',
                    'highlight' => '.action-new',
                ],
                [
                    'label' => 'label.guided_step_book_strip_creation_title',
                    'description' => 'description.guided_step_book_strip_creation_title',
                    'highlight' => '#Strip_title',
                ],
                [
                    'label' => 'label.guided_step_book_strip_creation_serie',
                    'description' => 'description.guided_step_book_strip_creation_serie',
                    'highlight' => '#Strip_serie_autocomplete + .ts-wrapper',
                ],
                [
                    'label' => 'label.guided_step_book_strip_creation_number',
                    'description' => 'description.guided_step_book_strip_creation_number',
                    'highlight' => '#Strip_number',
                ],
                [
                    'label' => 'label.guided_step_book_strip_creation_characters',
                    'description' => 'description.guided_step_book_strip_creation_characters',
                    'highlight' => '#Strip_characters',
                ],
                [
                    // The second tab of the form, where the drawing itself is attached (see StripCrudController)
                    'label' => 'label.guided_step_book_strip_creation_media_tab',
                    'description' => 'description.guided_step_book_strip_creation_media_tab',
                    'highlight' => '.form-tabs-tablist .nav-item:nth-child(2) .nav-link',
                ],
                [
                    'label' => 'label.guided_step_book_strip_creation_medias',
                    'description' => 'description.guided_step_book_strip_creation_medias',
                    'highlight' => '[data-strip-medias]',
                ],
                [
                    'label' => 'label.guided_step_book_strip_creation_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_book_strip_creation_done',
                    'description' => 'description.guided_step_book_strip_creation_done',
                ],
            ],
        ];
    }

    // A book close to another is copied rather than retyped: the copy carries everything that belongs to it, is saved at once and opens on its own edit screen, where it is waiting to be renamed (see BookDuplicator)
    private function duplicationProject(): array
    {
        return [
            'slug' => 'book-duplication',
            'label' => 'label.guided_project_book_duplication',
            'description' => 'description.guided_project_book_duplication',
            'translation_domain' => 'book',
            'order' => 6045,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_book_duplication_open',
                    'description' => 'description.guided_step_book_duplication_open',
                    'url' => $this->bookIndexUrl(),
                ],
                [
                    // The button asks for a confirmation, and lands the user on the edit screen of the copy
                    'label' => 'label.guided_step_book_duplication_duplicate',
                    'description' => 'description.guided_step_book_duplication_duplicate',
                    'highlight' => '.action-duplicate',
                ],
                [
                    'label' => 'label.guided_step_book_duplication_title',
                    'description' => 'description.guided_step_book_duplication_title',
                    'highlight' => '#Book_title',
                ],
                [
                    'label' => 'label.guided_step_book_duplication_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_book_duplication_done',
                    'description' => 'description.guided_step_book_duplication_done',
                ],
            ],
        ];
    }

    // The gesture no other content has: what has been published so far is set aside in a twin, and the book keeps its address, its ratings and its translations to receive the new text (see BookVersionPublisher)
    private function versionPublicationProject(): array
    {
        return [
            'slug' => 'book-version-publication',
            'label' => 'label.guided_project_book_version_publication',
            'description' => 'description.guided_project_book_version_publication',
            'translation_domain' => 'book',
            'order' => 6050,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_book_version_publication_open',
                    'description' => 'description.guided_step_book_version_publication_open',
                    'url' => $this->bookIndexUrl(),
                ],
                [
                    // The button asks for a confirmation, and lands the user on the edit screen of the book that stays
                    'label' => 'label.guided_step_book_version_publication_publish',
                    'description' => 'description.guided_step_book_version_publication_publish',
                    'highlight' => '.action-publishVersion',
                ],
                [
                    'label' => 'label.guided_step_book_version_publication_previous',
                    'description' => 'description.guided_step_book_version_publication_previous',
                    'highlight' => '#Book_previousVersion + .ts-wrapper',
                ],
                [
                    'label' => 'label.guided_step_book_version_publication_technical_tab',
                    'description' => 'description.guided_step_book_version_publication_technical_tab',
                    'highlight' => '.form-tabs-tablist .nav-item:nth-child(2) .nav-link',
                ],
                [
                    // The editions left with the twin, so the book that stays is waiting for the new one
                    'label' => 'label.guided_step_book_version_publication_editions',
                    'description' => 'description.guided_step_book_version_publication_editions',
                    'highlight' => '[data-book-editions] .field-collection-add-button',
                ],
                [
                    'label' => 'label.guided_step_book_version_publication_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_book_version_publication_done',
                    'description' => 'description.guided_step_book_version_publication_done',
                ],
            ],
        ];
    }

    // The switch that takes a book off the site without touching it: it stays in the catalog with its files and its blocks, and its page simply answers 404 - a different gesture from the trash, which the parcours ends by saying (see HideableTrait)
    private function hiddenProject(): array
    {
        return [
            'slug' => 'book-hidden',
            'label' => 'label.guided_project_book_hidden',
            'description' => 'description.guided_project_book_hidden',
            'translation_domain' => 'book',
            'order' => 6055,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_book_hidden_open',
                    'description' => 'description.guided_step_book_hidden_open',
                    'url' => $this->bookIndexUrl(),
                ],
                [
                    // The cell of the "hidden" column on the first row, EasyAdmin keying every index cell by the property it prints (see its crud/index.html.twig) - a boolean printed on the index carries no field id
                    'label' => 'label.guided_step_book_hidden_switch',
                    'description' => 'description.guided_step_book_hidden_switch',
                    'highlight' => 'td[data-column="hidden"]',
                ],
                [
                    'label' => 'label.guided_step_book_hidden_edit',
                    'description' => 'description.guided_step_book_hidden_edit',
                    'highlight' => '.action-edit',
                ],
                [
                    // The checkbox itself, which the form prints with its id, unlike the index cell above
                    'label' => 'label.guided_step_book_hidden_field',
                    'description' => 'description.guided_step_book_hidden_field',
                    'highlight' => '#Book_hidden',
                ],
                [
                    'label' => 'label.guided_step_book_hidden_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_book_hidden_done',
                    'description' => 'description.guided_step_book_hidden_done',
                ],
            ],
        ];
    }

    // Nothing is lost in one click: a book put aside keeps its page and its files, and the same screen shows what is in the trash - the parcours stops there, restoring and deleting for good being held at the admin's role and highlighting buttons an editor never sees (see TrashableCrudTrait)
    private function trashProject(): array
    {
        return [
            'slug' => 'book-trash',
            'label' => 'label.guided_project_book_trash',
            'description' => 'description.guided_project_book_trash',
            'translation_domain' => 'book',
            'order' => 6060,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_book_trash_open',
                    'description' => 'description.guided_step_book_trash_open',
                    'url' => $this->bookIndexUrl(),
                ],
                [
                    'label' => 'label.guided_step_book_trash_delete',
                    'description' => 'description.guided_step_book_trash_delete',
                    'highlight' => '.action-delete',
                ],
                [
                    'label' => 'label.guided_step_book_trash_switch',
                    'description' => 'description.guided_step_book_trash_switch',
                    'highlight' => '.action-trash',
                ],
                [
                    'label' => 'label.guided_step_book_trash_done',
                    'description' => 'description.guided_step_book_trash_done',
                ],
            ],
        ];
    }

    // The catalog leaves the site as a file, which is the admin's business and not the editor's: a raw dump of the whole table is not what composing a catalog needs, and the three exports sit a role above everything else here (see TrashableCrudTrait::configureActions)
    private function exportProject(): array
    {
        return [
            'slug' => 'book-export',
            'label' => 'label.guided_project_book_export',
            'description' => 'description.guided_project_book_export',
            'translation_domain' => 'book',
            'order' => 6070,
            'role' => $this->adminRoleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_book_export_open',
                    'description' => 'description.guided_step_book_export_open',
                    'url' => $this->bookIndexUrl(),
                ],
                [
                    // The button of the group, its three formats living in the menu it unfolds
                    'label' => 'label.guided_step_book_export_group',
                    'description' => 'description.guided_step_book_export_group',
                    'highlight' => '.action-group .dropdown-toggle',
                ],
                [
                    // Inside the menu the step before has just unfolded
                    'label' => 'label.guided_step_book_export_format',
                    'description' => 'description.guided_step_book_export_format',
                    'highlight' => '.action-exportCsv',
                ],
                [
                    // The header box, the row ones being numbered by their rank on the page and no stable target for a step
                    'label' => 'label.guided_step_book_export_select',
                    'description' => 'description.guided_step_book_export_select',
                    'highlight' => '#form-batch-checkbox-all',
                ],
                [
                    // The batch bar leaves its "d-none" only once a row is ticked, which the step before is what does
                    'label' => 'label.guided_step_book_export_selection',
                    'description' => 'description.guided_step_book_export_selection',
                    'highlight' => '.action-exportSelection',
                ],
                [
                    'label' => 'label.guided_step_book_export_done',
                    'description' => 'description.guided_step_book_export_done',
                ],
            ],
        ];
    }

    // The role every catalog screen sits behind, the same ConfigBundle entry its controllers read (see BookCrudController) - a parcours walking screens the user can't open reads as a broken one
    private function roleNeeded(): string
    {
        return (string) $this->configService->get('site-role-editor');
    }

    private function contributorIndexUrl(): string
    {
        return $this->indexUrl(ContributorCrudController::class);
    }

    // The bar the exports sit behind, a role above everything else on these screens (see TrashableCrudTrait::configureActions)
    private function adminRoleNeeded(): string
    {
        return (string) $this->configService->get('site-role-admin');
    }

    private function serieIndexUrl(): string
    {
        return $this->indexUrl(SerieCrudController::class);
    }

    private function bookIndexUrl(): string
    {
        return $this->indexUrl(BookCrudController::class);
    }

    private function stripIndexUrl(): string
    {
        return $this->indexUrl(StripCrudController::class);
    }

    private function indexUrl(string $controller): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController($controller)
            ->setAction(Action::INDEX)
            ->generateUrl();
    }
}
