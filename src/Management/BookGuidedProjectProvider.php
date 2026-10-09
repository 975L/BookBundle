<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Management;

use c975L\BookBundle\Controller\Management\BookCategoryCrudController;
use c975L\BookBundle\Controller\Management\BookCrudController;
use c975L\BookBundle\Controller\Management\BookSettingsCrudController;
use c975L\BookBundle\Controller\Management\CharacterCrudController;
use c975L\BookBundle\Controller\Management\ContributorCrudController;
use c975L\BookBundle\Controller\Management\SerieCrudController;
use c975L\BookBundle\Controller\Management\StripCrudController;
use c975L\ConfigBundle\Controller\Management\ConfigCrudController;
use c975L\ConfigBundle\Management\GuidedProjectProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

// This bundle's guided projects, running the 6000 block GuidedProjectProviderInterface reserves them - the same docblock stating every other bundle's, so a range is read there rather than recopied here. They follow the order a catalog is actually built in - the people are credited by the series and the books, the serie holds the books, so each comes before what names it. Only the opening step of each carries an url: from there the parcours walks the screen the user has been sent to, highlighting the button or the field they are meant to use next (see ConfigBundle's assets/js/guided-project.js). A field is pointed at through the widget the user actually sees, which is not always the one carrying the id: a choice or an association stays a native select only below UiBundle's autocomplete threshold (ChoiceAutocompleteExtension, 10 options), TomSelect taking it over above and clipping the select away behind "ts-hidden-accessible" - so a field whose option count is the catalog's own is named through its row, ".form-group:has(#Entity_property)", the only form both regimes answer to. An association calling autocomplete() is always a TomSelect, its select printed by CrudAutocompleteType under an inner field named "autocomplete" - hence the "_autocomplete" suffix those steps carry, and the "+ .ts-wrapper" naming the widget drawn next to it. TrixEditorType hides its textarea behind "d-none", and a collection prints no field id at all, being marked on its own row instead (see the "data-*" markers of the CRUD controllers)
class BookGuidedProjectProvider implements GuidedProjectProviderInterface
{
    // The edition just added: EasyAdmin unfolds the last row of a complex collection when it adds one
    private const string EDITION_ADDED = '[data-book-editions] .field-collection-item:last-child';

    // The edition the user unfolded among those already saved, which EasyAdmin prints folded
    private const string EDITION_OPEN = '[data-book-editions] .accordion-collapse.show';

    // The reader block just added: the last row of the open pane holding an "_data_autoAdvance", which is that kind's own field (see bookReaderProject())
    private const string READER_ROW = '.tab-pane.active .field-collection-item:has([id$="_data_autoAdvance"]):last-child';

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
            $this->categoryCreationProject(),
            $this->indexProject(),
            $this->bookCreationProject(),
            $this->mediaMoveProject(),
            $this->bookCompositionProject(),
            $this->bookListenProject(),
            $this->bookReaderProject(),
            $this->translationProject(),
            $this->sortingProject(),
            $this->characterCreationProject(),
            $this->stripCreationProject(),
            $this->duplicationProject(),
            $this->versionPublicationProject(),
            $this->hiddenProject(),
            $this->trashProject(),
            $this->exportProject(),
            $this->onixFeedProject(),
            $this->editionSaleProject(),
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
                    'narration' => 'narration.guided_step_book_contributor_creation_open',
                    'url' => $this->contributorIndexUrl(),
                ],
                [
                    'label' => 'label.guided_step_book_contributor_creation_new',
                    'description' => 'description.guided_step_book_contributor_creation_new',
                    'narration' => 'narration.guided_step_book_contributor_creation_new',
                    'highlight' => '.action-new',
                ],
                [
                    'label' => 'label.guided_step_book_contributor_creation_name',
                    'description' => 'description.guided_step_book_contributor_creation_name',
                    'narration' => 'narration.guided_step_book_contributor_creation_name',
                    'highlight' => '#Contributor_name',
                ],
                [
                    // The Trix editor, the textarea carrying the id being hidden behind it
                    'label' => 'label.guided_step_book_contributor_creation_summary',
                    'description' => 'description.guided_step_book_contributor_creation_summary',
                    'narration' => 'narration.guided_step_book_contributor_creation_summary',
                    'highlight' => 'trix-editor[input="Contributor_summary"]',
                ],
                [
                    'label' => 'label.guided_step_book_contributor_creation_website',
                    'description' => 'description.guided_step_book_contributor_creation_website',
                    'narration' => 'narration.guided_step_book_contributor_creation_website',
                    'highlight' => '#Contributor_website',
                ],
                [
                    // The marker laid on the row by ContributorCrudController, a collection printing no field id
                    'label' => 'label.guided_step_book_contributor_creation_portrait',
                    'description' => 'description.guided_step_book_contributor_creation_portrait',
                    'narration' => 'narration.guided_step_book_contributor_creation_portrait',
                    'highlight' => '[data-contributor-portraits]',
                ],
                [
                    // The same marker on the "Buy" collection, the person's own page at each store rather than one book's
                    'label' => 'label.guided_step_book_contributor_creation_links',
                    'description' => 'description.guided_step_book_contributor_creation_links',
                    'narration' => 'narration.guided_step_book_contributor_creation_links',
                    'highlight' => '[data-contributor-links]',
                ],
                [
                    'label' => 'label.guided_step_book_contributor_creation_save',
                    'narration' => 'narration.guided_step_book_contributor_creation_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_book_contributor_creation_done',
                    'description' => 'description.guided_step_book_contributor_creation_done',
                    'narration' => 'narration.guided_step_book_contributor_creation_done',
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
                    'narration' => 'narration.guided_step_book_serie_creation_open',
                    'url' => $this->serieIndexUrl(),
                ],
                [
                    'label' => 'label.guided_step_book_serie_creation_new',
                    'description' => 'description.guided_step_book_serie_creation_new',
                    'narration' => 'narration.guided_step_book_serie_creation_new',
                    'highlight' => '.action-new',
                ],
                [
                    'label' => 'label.guided_step_book_serie_creation_title',
                    'description' => 'description.guided_step_book_serie_creation_title',
                    'narration' => 'narration.guided_step_book_serie_creation_title',
                    'highlight' => '#Serie_title',
                ],
                [
                    // A two-value choice, rendered as a native select: it will never reach the autocomplete threshold
                    'label' => 'label.guided_step_book_serie_creation_kind',
                    'description' => 'description.guided_step_book_serie_creation_kind',
                    'narration' => 'narration.guided_step_book_serie_creation_kind',
                    'highlight' => '#Serie_kind',
                ],
                [
                    // The Trix editor, the textarea carrying the id being hidden behind it
                    'label' => 'label.guided_step_book_serie_creation_summary',
                    'description' => 'description.guided_step_book_serie_creation_summary',
                    'narration' => 'narration.guided_step_book_serie_creation_summary',
                    'highlight' => 'trix-editor[input="Serie_summary"]',
                ],
                [
                    // The TomSelect widget of the autocompleted association, whose select CrudAutocompleteType names after its own inner field (see the "_autocomplete" suffix)
                    'label' => 'label.guided_step_book_serie_creation_author',
                    'description' => 'description.guided_step_book_serie_creation_author',
                    'narration' => 'narration.guided_step_book_serie_creation_author',
                    'highlight' => '#Serie_author_autocomplete + .ts-wrapper',
                ],
                [
                    'label' => 'label.guided_step_book_serie_creation_covers',
                    'description' => 'description.guided_step_book_serie_creation_covers',
                    'narration' => 'narration.guided_step_book_serie_creation_covers',
                    'highlight' => '[data-serie-covers]',
                ],
                [
                    'label' => 'label.guided_step_book_serie_creation_save',
                    'narration' => 'narration.guided_step_book_serie_creation_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_book_serie_creation_done',
                    'description' => 'description.guided_step_book_serie_creation_done',
                    'narration' => 'narration.guided_step_book_serie_creation_done',
                ],
            ],
        ];
    }

    // The categories come before the books too, a book being filed under them from its own screen - and the whole parcours is worth nothing to a site that files its catalog by serie alone, which is why it says so on its very first step
    private function categoryCreationProject(): array
    {
        return [
            'slug' => 'book-category-creation',
            'label' => 'label.guided_project_book_category_creation',
            'description' => 'description.guided_project_book_category_creation',
            'translation_domain' => 'book',
            'order' => 6015,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_book_category_creation_open',
                    'description' => 'description.guided_step_book_category_creation_open',
                    'narration' => 'narration.guided_step_book_category_creation_open',
                    'url' => $this->categoryIndexUrl(),
                ],
                [
                    'label' => 'label.guided_step_book_category_creation_new',
                    'description' => 'description.guided_step_book_category_creation_new',
                    'narration' => 'narration.guided_step_book_category_creation_new',
                    'highlight' => '.action-new',
                ],
                [
                    'label' => 'label.guided_step_book_category_creation_title',
                    'description' => 'description.guided_step_book_category_creation_title',
                    'narration' => 'narration.guided_step_book_category_creation_title',
                    'highlight' => '#BookCategory_title',
                ],
                [
                    // The Trix editor, the textarea carrying the id being hidden behind it
                    'label' => 'label.guided_step_book_category_creation_summary',
                    'description' => 'description.guided_step_book_category_creation_summary',
                    'narration' => 'narration.guided_step_book_category_creation_summary',
                    'highlight' => 'trix-editor[input="BookCategory_summary"]',
                ],
                [
                    // The group of the three classifications, a compound field printing no id of its own
                    'label' => 'label.guided_step_book_category_creation_code',
                    'description' => 'description.guided_step_book_category_creation_code',
                    'narration' => 'narration.guided_step_book_category_creation_code',
                    'highlight' => '[data-book-category-codes]',
                ],
                [
                    'label' => 'label.guided_step_book_category_creation_save',
                    'narration' => 'narration.guided_step_book_category_creation_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_book_category_creation_done',
                    'description' => 'description.guided_step_book_category_creation_done',
                    'narration' => 'narration.guided_step_book_category_creation_done',
                ],
            ],
        ];
    }

    // The page the catalog opens on, set once before the books are filled in: its sentence and the blocks under it are the first thing a visitor reads, and the only screen of this bundle editing a single row rather than a list
    private function indexProject(): array
    {
        return [
            'slug' => 'book-index',
            'label' => 'label.guided_project_book_index',
            'description' => 'description.guided_project_book_index',
            'translation_domain' => 'book',
            // Between the categories and the book: the catalog's own page is what one settles before filling the listing it prints
            'order' => 6017,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_book_index_open',
                    'description' => 'description.guided_step_book_index_open',
                    'narration' => 'narration.guided_step_book_index_open',
                    // The index action redirects straight to the single row, so the screen this opens on is already the form - no edit step to walk through
                    'url' => $this->indexUrl(BookSettingsCrudController::class),
                ],
                [
                    'label' => 'label.guided_step_book_index_intro',
                    'description' => 'description.guided_step_book_index_intro',
                    'narration' => 'narration.guided_step_book_index_intro',
                    'highlight' => '#BookSettings_intro',
                ],
                [
                    // The collection's own row, a collection printing no field id at all
                    'label' => 'label.guided_step_book_index_blocks',
                    'description' => 'description.guided_step_book_index_blocks',
                    'narration' => 'narration.guided_step_book_index_blocks',
                    'highlight' => '[data-book-settings-blocks]',
                ],
                [
                    'label' => 'label.guided_step_book_index_save',
                    'description' => 'description.guided_step_book_index_save',
                    'narration' => 'narration.guided_step_book_index_save',
                    'highlight' => '.action-saveAndReturn',
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
                    'narration' => 'narration.guided_step_book_creation_open',
                    'url' => $this->bookIndexUrl(),
                ],
                [
                    'label' => 'label.guided_step_book_creation_new',
                    'description' => 'description.guided_step_book_creation_new',
                    'narration' => 'narration.guided_step_book_creation_new',
                    'highlight' => '.action-new',
                ],
                [
                    'label' => 'label.guided_step_book_creation_title',
                    'description' => 'description.guided_step_book_creation_title',
                    'narration' => 'narration.guided_step_book_creation_title',
                    'highlight' => '#Book_title',
                ],
                [
                    'label' => 'label.guided_step_book_creation_serie',
                    'description' => 'description.guided_step_book_creation_serie',
                    'narration' => 'narration.guided_step_book_creation_serie',
                    'highlight' => '#Book_serie_autocomplete + .ts-wrapper',
                ],
                [
                    // The same autocompleted association as the serie above, this one taking several at once (see BookCrudController)
                    'label' => 'label.guided_step_book_creation_categories',
                    'description' => 'description.guided_step_book_creation_categories',
                    'narration' => 'narration.guided_step_book_creation_categories',
                    'highlight' => '#Book_categories_autocomplete + .ts-wrapper',
                ],
                [
                    'label' => 'label.guided_step_book_creation_published',
                    'description' => 'description.guided_step_book_creation_published',
                    'narration' => 'narration.guided_step_book_creation_published',
                    'highlight' => '#Book_published',
                ],
                [
                    // Same autocompleted association as the serie's, left empty for a book crediting whoever its serie does (see Book::getEffectiveAuthor())
                    'label' => 'label.guided_step_book_creation_author',
                    'description' => 'description.guided_step_book_creation_author',
                    'narration' => 'narration.guided_step_book_creation_author',
                    'highlight' => '#Book_author_autocomplete + .ts-wrapper',
                ],
                [
                    // Everyone else the book credits, one row per person and per part - the collection printing no field id, the row's own marker is what the tour points at (see BookCrudController)
                    'label' => 'label.guided_step_book_creation_contributors',
                    'description' => 'description.guided_step_book_creation_contributors',
                    'narration' => 'narration.guided_step_book_creation_contributors',
                    'highlight' => '[data-book-contributors]',
                ],
                [
                    // The second tab of the form, an ISBN belonging to an edition and no longer to the book itself (see BookCrudController)
                    'label' => 'label.guided_step_book_creation_page_tab',
                    'description' => 'description.guided_step_book_creation_page_tab',
                    'narration' => 'narration.guided_step_book_creation_page_tab',
                    'highlight' => '.form-tabs-tablist .nav-item:nth-child(2) .nav-link',
                ],
                [
                    // The editions are edited right here: a collection prints no field id, so the row's own marker is what the tour points at (see BookCrudController)
                    'label' => 'label.guided_step_book_creation_editions',
                    'description' => 'description.guided_step_book_creation_editions',
                    'narration' => 'narration.guided_step_book_creation_editions',
                    'highlight' => '[data-book-editions]',
                ],
                [
                    // The button unfolding one more edition, its price, the files it is sold as and its channels set inside the panel it opens
                    'label' => 'label.guided_step_book_creation_editions_add',
                    'description' => 'description.guided_step_book_creation_editions_add',
                    'narration' => 'narration.guided_step_book_creation_editions_add',
                    'highlight' => '[data-book-editions] .field-collection-add-button',
                ],
                [
                    // Where the edition just added goes, EasyAdmin unfolding a new row of a complex collection (see BookChannel)
                    'label' => 'label.guided_step_book_creation_edition_channels',
                    'description' => 'description.guided_step_book_creation_edition_channels',
                    'narration' => 'narration.guided_step_book_creation_edition_channels',
                    'highlight' => self::EDITION_ADDED . ' [id$="_channels"]',
                ],
                [
                    'label' => 'label.guided_step_book_creation_save',
                    'narration' => 'narration.guided_step_book_creation_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_book_creation_done',
                    'description' => 'description.guided_step_book_creation_done',
                    'narration' => 'narration.guided_step_book_creation_done',
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
                    'narration' => 'narration.guided_step_book_media_move_open',
                    'url' => $this->bookIndexUrl(),
                ],
                [
                    // An existing book, the markers of the gesture being laid on its files only once it has an id (see BookMediaMoveRowAttrBuilder::build())
                    'label' => 'label.guided_step_book_media_move_edit',
                    'description' => 'description.guided_step_book_media_move_edit',
                    'narration' => 'narration.guided_step_book_media_move_edit',
                    'highlight' => '.action-edit',
                ],
                [
                    'label' => 'label.guided_step_book_media_move_tab',
                    'description' => 'description.guided_step_book_media_move_tab',
                    'narration' => 'narration.guided_step_book_media_move_tab',
                    'highlight' => '.form-tabs-tablist .nav-item:nth-child(2) .nav-link',
                ],
                [
                    'label' => 'label.guided_step_book_media_move_pages',
                    'description' => 'description.guided_step_book_media_move_pages',
                    'narration' => 'narration.guided_step_book_media_move_pages',
                    'highlight' => '[data-ui-move-target="page"]',
                ],
                [
                    // The grip UiBundle's ea-sortable.js lays on the header bar of each row
                    'label' => 'label.guided_step_book_media_move_handle',
                    'description' => 'description.guided_step_book_media_move_handle',
                    'narration' => 'narration.guided_step_book_media_move_handle',
                    'highlight' => '[data-ui-move-target="page"] .ui-sort-handle',
                ],
                [
                    'label' => 'label.guided_step_book_media_move_done',
                    'description' => 'description.guided_step_book_media_move_done',
                    'narration' => 'narration.guided_step_book_media_move_done',
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
                    'narration' => 'narration.guided_step_book_composition_open',
                    'url' => $this->bookIndexUrl(),
                ],
                [
                    'label' => 'label.guided_step_book_composition_edit',
                    'description' => 'description.guided_step_book_composition_edit',
                    'narration' => 'narration.guided_step_book_composition_edit',
                    'highlight' => '.action-edit',
                ],
                [
                    // The last tab of the form, whatever the tabs before it
                    'label' => 'label.guided_step_book_composition_tab',
                    'description' => 'description.guided_step_book_composition_tab',
                    'narration' => 'narration.guided_step_book_composition_tab',
                    'highlight' => '.form-tabs-tablist .nav-item:last-child .nav-link',
                ],
                [
                    // The collection's add button in the active pane, adding a row opening the palette the step after this one points into (see UiBundle's block-collection.js)
                    'label' => 'label.guided_step_book_composition_add',
                    'description' => 'description.guided_step_book_composition_add',
                    'narration' => 'narration.guided_step_book_composition_add',
                    // Scoped to the tab the step before it opens: a book form carries three block collections, one per tab, and the first of them - the one an unscoped selector finds - sits on a tab nobody is looking at. Named by the pane Bootstrap marks active rather than by its id, which EasyAdmin slugs from the translated label - "tab-blocs" in French, "tab-blocks" in English, "tab-bloques" in Spanish. The add button of a block's own medias, rendered inside its row and so before the collection's own, is left out
                    'highlight' => '.tab-pane.active .field-collection-add-button:not(.field-collection-item .field-collection-add-button)',
                ],
                [
                    // The silhouette of one listing kind in the palette, each tile carrying the kind it stands for (see UiBundle's block-picker.js). "book_books" stands for all of them, which read the same way
                    'label' => 'label.guided_step_book_composition_listing',
                    'description' => 'description.guided_step_book_composition_listing',
                    'narration' => 'narration.guided_step_book_composition_listing',
                    'highlight' => '.ui-block-picker [data-kind="book_books"]',
                ],
                [
                    // The heading a listing now carries of its own (see AbstractBookListingBlockType), which is what saves laying a "text_section" above the grid. Named by the row holding a "random" checkbox rather than by the title alone: every kind prints a "_data_title", where "random" is this bundle's listing kinds and nothing else - UiBundle's own collection block only offers it as a choice value. The last such row, a new one being appended after the blocks the book already holds (see EasyAdmin's field-collection.js)
                    'label' => 'label.guided_step_book_composition_heading',
                    'description' => 'description.guided_step_book_composition_heading',
                    'narration' => 'narration.guided_step_book_composition_heading',
                    'highlight' => '.tab-pane.active .field-collection-item:has([id$="_data_random"]):last-child [id$="_data_title"]',
                ],
                [
                    'label' => 'label.guided_step_book_composition_save',
                    'narration' => 'narration.guided_step_book_composition_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_book_composition_done',
                    'description' => 'description.guided_step_book_composition_done',
                    'narration' => 'narration.guided_step_book_composition_done',
                ],
            ],
        ];
    }

    // The listening page plays the book's own files, no block involved: its pages, its recording and the timecodes turning the first along the second, all three on the "Page" tab (see ListenController)
    private function bookListenProject(): array
    {
        return [
            'slug' => 'book-listen',
            'label' => 'label.guided_project_book_listen',
            'description' => 'description.guided_project_book_listen',
            'translation_domain' => 'book',
            'order' => 6031,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_book_listen_open',
                    'description' => 'description.guided_step_book_listen_open',
                    'narration' => 'narration.guided_step_book_listen_open',
                    'url' => $this->bookIndexUrl(),
                ],
                [
                    // An existing book, the pages' marker being laid only once it has an id (see BookMediaMoveRowAttrBuilder::build())
                    'label' => 'label.guided_step_book_listen_edit',
                    'description' => 'description.guided_step_book_listen_edit',
                    'narration' => 'narration.guided_step_book_listen_edit',
                    'highlight' => '.action-edit',
                ],
                [
                    'label' => 'label.guided_step_book_listen_tab',
                    'description' => 'description.guided_step_book_listen_tab',
                    'narration' => 'narration.guided_step_book_listen_tab',
                    'highlight' => '.form-tabs-tablist .nav-item:nth-child(2) .nav-link',
                ],
                [
                    'label' => 'label.guided_step_book_listen_pages',
                    'description' => 'description.guided_step_book_listen_pages',
                    'narration' => 'narration.guided_step_book_listen_pages',
                    'highlight' => '[data-ui-move-target="page"]',
                ],
                [
                    'label' => 'label.guided_step_book_listen_audios',
                    'description' => 'description.guided_step_book_listen_audios',
                    'narration' => 'narration.guided_step_book_listen_audios',
                    'highlight' => '[data-book-audios]',
                ],
                [
                    'label' => 'label.guided_step_book_listen_cues',
                    'description' => 'description.guided_step_book_listen_cues',
                    'narration' => 'narration.guided_step_book_listen_cues',
                    'highlight' => '[data-book-cues]',
                ],
                [
                    'label' => 'label.guided_step_book_listen_save',
                    'narration' => 'narration.guided_step_book_listen_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    // The page answers only once a site names its first segment, "book-route-listen" being empty on a fresh install (see config/configs.json)
                    'label' => 'label.guided_step_book_listen_done',
                    'description' => 'description.guided_step_book_listen_done',
                    'narration' => 'narration.guided_step_book_listen_done',
                ],
            ],
        ];
    }

    // The one block of this bundle whose content is its own attached medias: the pages of the album and the recording turned along with them. Its fields live inside a block row, whose ids are numbered by the collection ("Book_blocks_0_data_id"), so each is named by the end of its id - scoped to the last row holding an "_data_autoAdvance", the one just added, several UiBundle kinds printing a "_data_id" of their own and a book possibly holding a reader already
    private function bookReaderProject(): array
    {
        return [
            'slug' => 'book-reader',
            'label' => 'label.guided_project_book_reader',
            'description' => 'description.guided_project_book_reader',
            'translation_domain' => 'book',
            'order' => 6033,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_book_reader_open',
                    'description' => 'description.guided_step_book_reader_open',
                    'narration' => 'narration.guided_step_book_reader_open',
                    'url' => $this->bookIndexUrl(),
                ],
                [
                    'label' => 'label.guided_step_book_reader_edit',
                    'description' => 'description.guided_step_book_reader_edit',
                    'narration' => 'narration.guided_step_book_reader_edit',
                    'highlight' => '.action-edit',
                ],
                [
                    // The last tab of the form, whatever the tabs before it
                    'label' => 'label.guided_step_book_reader_tab',
                    'description' => 'description.guided_step_book_reader_tab',
                    'narration' => 'narration.guided_step_book_reader_tab',
                    'highlight' => '.form-tabs-tablist .nav-item:last-child .nav-link',
                ],
                [
                    // The collection's add button in the active pane, adding a row opening the palette the step after this one points into (see UiBundle's block-collection.js)
                    'label' => 'label.guided_step_book_reader_add',
                    'description' => 'description.guided_step_book_reader_add',
                    'narration' => 'narration.guided_step_book_reader_add',
                    'highlight' => '.tab-pane.active .field-collection-add-button:not(.field-collection-item .field-collection-add-button)',
                ],
                [
                    // The silhouette of the kind in the palette, each tile carrying the kind it stands for (see UiBundle's block-picker.js)
                    'label' => 'label.guided_step_book_reader_kind',
                    'description' => 'description.guided_step_book_reader_kind',
                    'narration' => 'narration.guided_step_book_reader_kind',
                    'highlight' => '.ui-block-picker [data-kind="book_reader"]',
                ],
                [
                    // The multi-file input the kind declares (media_multi_upload in services.yaml), the pages and the recording going up in one go
                    'label' => 'label.guided_step_book_reader_medias',
                    'description' => 'description.guided_step_book_reader_medias',
                    'narration' => 'narration.guided_step_book_reader_medias',
                    'highlight' => self::READER_ROW . ' input[type="file"][multiple]',
                ],
                [
                    'label' => 'label.guided_step_book_reader_id',
                    'description' => 'description.guided_step_book_reader_id',
                    'narration' => 'narration.guided_step_book_reader_id',
                    'highlight' => self::READER_ROW . ' [id$="_data_id"]',
                ],
                [
                    // Reached through its prototype rather than through an id: EasyAdmin's collection_widget replaces form_widget_compound entirely and renders no id at all, so only the placeholder it carries tells this collection from the block's medias
                    'label' => 'label.guided_step_book_reader_cues',
                    'description' => 'description.guided_step_book_reader_cues',
                    'narration' => 'narration.guided_step_book_reader_cues',
                    'highlight' => self::READER_ROW . ' [data-ea-collection-field][data-prototype*="_data_cues_"]',
                ],
                [
                    'label' => 'label.guided_step_book_reader_auto_advance',
                    'description' => 'description.guided_step_book_reader_auto_advance',
                    'narration' => 'narration.guided_step_book_reader_auto_advance',
                    'highlight' => self::READER_ROW . ' [id$="_data_autoAdvance"]',
                ],
                [
                    'label' => 'label.guided_step_book_reader_save',
                    'description' => 'description.guided_step_book_reader_save',
                    'narration' => 'narration.guided_step_book_reader_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    // The listening page does not read the block: it turns the pages on the timecodes file laid on the book itself (see bookListenProject())
                    'label' => 'label.guided_step_book_reader_listen',
                    'description' => 'description.guided_step_book_reader_listen',
                    'narration' => 'narration.guided_step_book_reader_listen',
                ],
                [
                    'label' => 'label.guided_step_book_reader_done',
                    'description' => 'description.guided_step_book_reader_done',
                    'narration' => 'narration.guided_step_book_reader_done',
                ],
            ],
        ];
    }

    // A book is translated once it is written and composed: the parcours opens its first language screen from the list, the tabs moving between the others (see ContentLocaleCrudTrait)
    private function translationProject(): array
    {
        return [
            'slug' => 'book-translation',
            'label' => 'label.guided_project_book_translation',
            'description' => 'description.guided_project_book_translation',
            'translation_domain' => 'book',
            'order' => 6034,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_book_translation_open',
                    'description' => 'description.guided_step_book_translation_open',
                    'narration' => 'narration.guided_step_book_translation_open',
                    'url' => $this->bookIndexUrl(),
                ],
                [
                    // Shown only where the site declares more than one language, like the tabs it leads to (see ContentLocaleCrudTrait::translateAction())
                    'label' => 'label.guided_step_book_translation_action',
                    'description' => 'description.guided_step_book_translation_action',
                    'narration' => 'narration.guided_step_book_translation_action',
                    'highlight' => '.action-translate',
                ],
                [
                    // Posted by ConfigBundle's own tab strip, which is what carries the "?contenu=xx" the language screens are read at
                    'label' => 'label.guided_step_book_translation_locale',
                    'description' => 'description.guided_step_book_translation_locale',
                    'narration' => 'narration.guided_step_book_translation_locale',
                    'highlight' => '[data-content-locales]',
                ],
                [
                    'label' => 'label.guided_step_book_translation_title',
                    'description' => 'description.guided_step_book_translation_title',
                    'narration' => 'narration.guided_step_book_translation_title',
                    'highlight' => '#Book_title',
                ],
                [
                    // A plain textarea on a language screen, Donovan under it, where the writing screen hides its own behind Trix (see ContentLocaleCrudTrait::translationFields())
                    'label' => 'label.guided_step_book_translation_summary',
                    'description' => 'description.guided_step_book_translation_summary',
                    'narration' => 'narration.guided_step_book_translation_summary',
                    'highlight' => '#Book_summary',
                ],
                [
                    'label' => 'label.guided_step_book_translation_save',
                    'narration' => 'narration.guided_step_book_translation_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_book_translation_done',
                    'description' => 'description.guided_step_book_translation_done',
                    'narration' => 'narration.guided_step_book_translation_done',
                ],
            ],
        ];
    }

    // The order the public pages follow is laid by dragging the rows here, and nothing on the screen says so - the series, the people, the categories and the characters are sorted this way, their indexes being the four declaring the reorder markers (see serie_crud_index.html.twig, contributor_crud_index.html.twig, category_crud_index.html.twig and character_crud_index.html.twig)
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
                    'narration' => 'narration.guided_step_book_sorting_open',
                    'url' => $this->serieIndexUrl(),
                ],
                [
                    // EasyAdmin keys every index cell by the property it prints (see its crud/index.html.twig)
                    'label' => 'label.guided_step_book_sorting_column',
                    'description' => 'description.guided_step_book_sorting_column',
                    'narration' => 'narration.guided_step_book_sorting_column',
                    'highlight' => 'td[data-column="position"]',
                ],
                [
                    // The grip UiBundle's ea-index-sort.js lays in that cell at mount time
                    'label' => 'label.guided_step_book_sorting_handle',
                    'description' => 'description.guided_step_book_sorting_handle',
                    'narration' => 'narration.guided_step_book_sorting_handle',
                    'highlight' => 'td[data-column="position"] .ui-sort-handle',
                ],
                [
                    'label' => 'label.guided_step_book_sorting_done',
                    'description' => 'description.guided_step_book_sorting_done',
                    'narration' => 'narration.guided_step_book_sorting_done',
                ],
            ],
        ];
    }

    // Before the planches and not after them: a planche says who speaks by picking among the serie's own people, so a catalog with no character yet sends the editor back here mid-form
    private function characterCreationProject(): array
    {
        return [
            'slug' => 'book-character-creation',
            'label' => 'label.guided_project_book_character_creation',
            'description' => 'description.guided_project_book_character_creation',
            'translation_domain' => 'book',
            'order' => 6037,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_book_character_creation_open',
                    'description' => 'description.guided_step_book_character_creation_open',
                    'narration' => 'narration.guided_step_book_character_creation_open',
                    'url' => $this->characterIndexUrl(),
                ],
                [
                    'label' => 'label.guided_step_book_character_creation_new',
                    'description' => 'description.guided_step_book_character_creation_new',
                    'narration' => 'narration.guided_step_book_character_creation_new',
                    'highlight' => '.action-new',
                ],
                [
                    // A plain association with no autocomplete(), so it stays a native select below the threshold and becomes a TomSelect above it: its row is what both regimes answer to
                    'label' => 'label.guided_step_book_character_creation_serie',
                    'description' => 'description.guided_step_book_character_creation_serie',
                    'narration' => 'narration.guided_step_book_character_creation_serie',
                    'highlight' => '.form-group:has(#Character_serie)',
                ],
                [
                    'label' => 'label.guided_step_book_character_creation_name',
                    'description' => 'description.guided_step_book_character_creation_name',
                    'narration' => 'narration.guided_step_book_character_creation_name',
                    'highlight' => '#Character_name',
                ],
                [
                    'label' => 'label.guided_step_book_character_creation_slug',
                    'description' => 'description.guided_step_book_character_creation_slug',
                    'narration' => 'narration.guided_step_book_character_creation_slug',
                    'highlight' => '#Character_slug',
                ],
                [
                    'label' => 'label.guided_step_book_character_creation_group',
                    'description' => 'description.guided_step_book_character_creation_group',
                    'narration' => 'narration.guided_step_book_character_creation_group',
                    'highlight' => '#Character_groupName',
                ],
                [
                    // TrixEditorType hides its textarea behind "d-none", so the row is what carries the outline
                    'label' => 'label.guided_step_book_character_creation_presentation',
                    'description' => 'description.guided_step_book_character_creation_presentation',
                    'narration' => 'narration.guided_step_book_character_creation_presentation',
                    'highlight' => '.form-group:has(#Character_presentation)',
                ],
                [
                    'label' => 'label.guided_step_book_character_creation_portrait',
                    'description' => 'description.guided_step_book_character_creation_portrait',
                    'narration' => 'narration.guided_step_book_character_creation_portrait',
                    'highlight' => '[data-character-portraits]',
                ],
                [
                    'label' => 'label.guided_step_book_character_creation_save',
                    'narration' => 'narration.guided_step_book_character_creation_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_book_character_creation_done',
                    'description' => 'description.guided_step_book_character_creation_done',
                    'narration' => 'narration.guided_step_book_character_creation_done',
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
                    'narration' => 'narration.guided_step_book_strip_creation_open',
                    'url' => $this->stripIndexUrl(),
                ],
                [
                    'label' => 'label.guided_step_book_strip_creation_new',
                    'description' => 'description.guided_step_book_strip_creation_new',
                    'narration' => 'narration.guided_step_book_strip_creation_new',
                    'highlight' => '.action-new',
                ],
                [
                    'label' => 'label.guided_step_book_strip_creation_title',
                    'description' => 'description.guided_step_book_strip_creation_title',
                    'narration' => 'narration.guided_step_book_strip_creation_title',
                    'highlight' => '#Strip_title',
                ],
                [
                    'label' => 'label.guided_step_book_strip_creation_serie',
                    'description' => 'description.guided_step_book_strip_creation_serie',
                    'narration' => 'narration.guided_step_book_strip_creation_serie',
                    'highlight' => '#Strip_serie_autocomplete + .ts-wrapper',
                ],
                [
                    'label' => 'label.guided_step_book_strip_creation_number',
                    'description' => 'description.guided_step_book_strip_creation_number',
                    'narration' => 'narration.guided_step_book_strip_creation_number',
                    'highlight' => '#Strip_number',
                ],
                [
                    'label' => 'label.guided_step_book_strip_creation_characters',
                    'description' => 'description.guided_step_book_strip_creation_characters',
                    'narration' => 'narration.guided_step_book_strip_creation_characters',
                    'highlight' => '.form-group:has(#Strip_characters)',
                ],
                [
                    // The second tab of the form, where the drawing itself is attached (see StripCrudController)
                    'label' => 'label.guided_step_book_strip_creation_media_tab',
                    'description' => 'description.guided_step_book_strip_creation_media_tab',
                    'narration' => 'narration.guided_step_book_strip_creation_media_tab',
                    'highlight' => '.form-tabs-tablist .nav-item:nth-child(2) .nav-link',
                ],
                [
                    'label' => 'label.guided_step_book_strip_creation_medias',
                    'description' => 'description.guided_step_book_strip_creation_medias',
                    'narration' => 'narration.guided_step_book_strip_creation_medias',
                    'highlight' => '[data-strip-medias]',
                ],
                [
                    'label' => 'label.guided_step_book_strip_creation_save',
                    'narration' => 'narration.guided_step_book_strip_creation_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_book_strip_creation_done',
                    'description' => 'description.guided_step_book_strip_creation_done',
                    'narration' => 'narration.guided_step_book_strip_creation_done',
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
                    'narration' => 'narration.guided_step_book_duplication_open',
                    'url' => $this->bookIndexUrl(),
                ],
                [
                    // The button asks for a confirmation, and lands the user on the edit screen of the copy
                    'label' => 'label.guided_step_book_duplication_duplicate',
                    'description' => 'description.guided_step_book_duplication_duplicate',
                    'narration' => 'narration.guided_step_book_duplication_duplicate',
                    'highlight' => '.action-duplicate',
                ],
                [
                    'label' => 'label.guided_step_book_duplication_title',
                    'description' => 'description.guided_step_book_duplication_title',
                    'narration' => 'narration.guided_step_book_duplication_title',
                    'highlight' => '#Book_title',
                ],
                [
                    'label' => 'label.guided_step_book_duplication_save',
                    'narration' => 'narration.guided_step_book_duplication_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_book_duplication_done',
                    'description' => 'description.guided_step_book_duplication_done',
                    'narration' => 'narration.guided_step_book_duplication_done',
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
                    'narration' => 'narration.guided_step_book_version_publication_open',
                    'url' => $this->bookIndexUrl(),
                ],
                [
                    // The button asks for a confirmation, and lands the user on the edit screen of the book that stays
                    'label' => 'label.guided_step_book_version_publication_publish',
                    'description' => 'description.guided_step_book_version_publication_publish',
                    'narration' => 'narration.guided_step_book_version_publication_publish',
                    'highlight' => '.action-publishVersion',
                ],
                [
                    // The whole row of the field rather than its select: an association is a native select only under UiBundle's autocomplete threshold, and this one lists every living book
                    'label' => 'label.guided_step_book_version_publication_previous',
                    'description' => 'description.guided_step_book_version_publication_previous',
                    'narration' => 'narration.guided_step_book_version_publication_previous',
                    'highlight' => '.form-group:has(#Book_previousVersion)',
                ],
                [
                    'label' => 'label.guided_step_book_version_publication_page_tab',
                    'description' => 'description.guided_step_book_version_publication_page_tab',
                    'narration' => 'narration.guided_step_book_version_publication_page_tab',
                    'highlight' => '.form-tabs-tablist .nav-item:nth-child(2) .nav-link',
                ],
                [
                    // The editions left with the twin, so the book that stays is waiting for the new one
                    'label' => 'label.guided_step_book_version_publication_editions',
                    'description' => 'description.guided_step_book_version_publication_editions',
                    'narration' => 'narration.guided_step_book_version_publication_editions',
                    'highlight' => '[data-book-editions] .field-collection-add-button',
                ],
                [
                    // The pages, the flipbook and the timecodes left with the twin too, showing the text as it came out (see BookVersionPublisher::moveMedias())
                    'label' => 'label.guided_step_book_version_publication_pages',
                    'description' => 'description.guided_step_book_version_publication_pages',
                    'narration' => 'narration.guided_step_book_version_publication_pages',
                    'highlight' => '[data-ui-move-target="page"]',
                ],
                [
                    'label' => 'label.guided_step_book_version_publication_save',
                    'narration' => 'narration.guided_step_book_version_publication_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_book_version_publication_done',
                    'description' => 'description.guided_step_book_version_publication_done',
                    'narration' => 'narration.guided_step_book_version_publication_done',
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
                    'narration' => 'narration.guided_step_book_hidden_open',
                    'url' => $this->bookIndexUrl(),
                ],
                [
                    // The cell of the "hidden" column on the first row, EasyAdmin keying every index cell by the property it prints (see its crud/index.html.twig) - a boolean printed on the index carries no field id
                    'label' => 'label.guided_step_book_hidden_switch',
                    'description' => 'description.guided_step_book_hidden_switch',
                    'narration' => 'narration.guided_step_book_hidden_switch',
                    'highlight' => 'td[data-column="hidden"]',
                ],
                [
                    'label' => 'label.guided_step_book_hidden_edit',
                    'description' => 'description.guided_step_book_hidden_edit',
                    'narration' => 'narration.guided_step_book_hidden_edit',
                    'highlight' => '.action-edit',
                ],
                [
                    // The checkbox itself, which the form prints with its id, unlike the index cell above
                    'label' => 'label.guided_step_book_hidden_field',
                    'description' => 'description.guided_step_book_hidden_field',
                    'narration' => 'narration.guided_step_book_hidden_field',
                    'highlight' => '#Book_hidden',
                ],
                [
                    'label' => 'label.guided_step_book_hidden_save',
                    'narration' => 'narration.guided_step_book_hidden_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_book_hidden_done',
                    'description' => 'description.guided_step_book_hidden_done',
                    'narration' => 'narration.guided_step_book_hidden_done',
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
                    'narration' => 'narration.guided_step_book_trash_open',
                    'url' => $this->bookIndexUrl(),
                ],
                [
                    'label' => 'label.guided_step_book_trash_delete',
                    'description' => 'description.guided_step_book_trash_delete',
                    'narration' => 'narration.guided_step_book_trash_delete',
                    'highlight' => '.action-delete',
                ],
                [
                    'label' => 'label.guided_step_book_trash_switch',
                    'description' => 'description.guided_step_book_trash_switch',
                    'narration' => 'narration.guided_step_book_trash_switch',
                    'highlight' => '.action-trash',
                ],
                [
                    'label' => 'label.guided_step_book_trash_done',
                    'description' => 'description.guided_step_book_trash_done',
                    'narration' => 'narration.guided_step_book_trash_done',
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
                    'narration' => 'narration.guided_step_book_export_open',
                    'url' => $this->bookIndexUrl(),
                ],
                [
                    // The button of the group, its three formats living in the menu it unfolds
                    'label' => 'label.guided_step_book_export_group',
                    'description' => 'description.guided_step_book_export_group',
                    'narration' => 'narration.guided_step_book_export_group',
                    'highlight' => '.action-export',
                ],
                [
                    // The button unfolding the menu and not an entry inside it: EasyAdmin lays no class of its own on the entries of a group, and a menu closes as soon as the panel is clicked - so the three formats are named by the voice
                    'label' => 'label.guided_step_book_export_format',
                    'description' => 'description.guided_step_book_export_format',
                    'narration' => 'narration.guided_step_book_export_format',
                    'highlight' => '.action-export',
                ],
                [
                    // The header box, the row ones being numbered by their rank on the page and no stable target for a step
                    'label' => 'label.guided_step_book_export_select',
                    'description' => 'description.guided_step_book_export_select',
                    'narration' => 'narration.guided_step_book_export_select',
                    'highlight' => '#form-batch-checkbox-all',
                ],
                [
                    // The batch bar leaves its "d-none" only once a row is ticked, which the step before is what does
                    'label' => 'label.guided_step_book_export_selection',
                    'description' => 'description.guided_step_book_export_selection',
                    'narration' => 'narration.guided_step_book_export_selection',
                    'highlight' => '.action-exportSelection',
                ],
                [
                    'label' => 'label.guided_step_book_export_done',
                    'description' => 'description.guided_step_book_export_done',
                    'narration' => 'narration.guided_step_book_export_done',
                ],
            ],
        ];
    }

    // The feed the stores read, opened on ConfigBundle's screen where its two settings live - the prices and the codes it carries being named rather than walked to, as they sit on other screens (see BookOnixBuilder)
    private function onixFeedProject(): array
    {
        return [
            'slug' => 'book-onix-feed',
            'label' => 'label.guided_project_book_onix_feed',
            'description' => 'description.guided_project_book_onix_feed',
            'translation_domain' => 'book',
            'order' => 6075,
            // The bar ConfigCrudController states on its own actions
            'role' => $this->adminRoleNeeded(),
            'steps' => [
                [
                    // Scoped to the book group and searched on "ONIX", both settings carrying it in their translated label (see ConfigCrudController::createIndexQueryBuilder)
                    'label' => 'label.guided_step_book_onix_feed_open',
                    'description' => 'description.guided_step_book_onix_feed_open',
                    'narration' => 'narration.guided_step_book_onix_feed_open',
                    'url' => $this->adminUrlGenerator
                        ->unsetAll()
                        ->setController(ConfigCrudController::class)
                        ->setAction(Action::INDEX)
                        ->set('group', 'book')
                        ->set(EA::QUERY, 'ONIX')
                        ->generateUrl(),
                ],
                [
                    'label' => 'label.guided_step_book_onix_feed_edit',
                    'description' => 'description.guided_step_book_onix_feed_edit',
                    'narration' => 'narration.guided_step_book_onix_feed_edit',
                    'highlight' => '.action-edit',
                ],
                [
                    'label' => 'label.guided_step_book_onix_feed_route',
                    'description' => 'description.guided_step_book_onix_feed_route',
                    'narration' => 'narration.guided_step_book_onix_feed_route',
                    'highlight' => '[data-guided-config-value]',
                ],
                [
                    'label' => 'label.guided_step_book_onix_feed_publisher',
                    'description' => 'description.guided_step_book_onix_feed_publisher',
                    'narration' => 'narration.guided_step_book_onix_feed_publisher',
                ],
                [
                    'label' => 'label.guided_step_book_onix_feed_price',
                    'description' => 'description.guided_step_book_onix_feed_price',
                    'narration' => 'narration.guided_step_book_onix_feed_price',
                ],
                [
                    'label' => 'label.guided_step_book_onix_feed_codes',
                    'description' => 'description.guided_step_book_onix_feed_codes',
                    'narration' => 'narration.guided_step_book_onix_feed_codes',
                ],
                [
                    'label' => 'label.guided_step_book_onix_feed_done',
                    'description' => 'description.guided_step_book_onix_feed_done',
                    'narration' => 'narration.guided_step_book_onix_feed_done',
                ],
            ],
        ];
    }

    // An edition sold as files: its files and their prices dropped in its panel, its covers beside them, its channels ticked, and the book saved - which writes the product into the shop (see BookShopPublishSubscriber). On an existing book, an edition being sold once written
    private function editionSaleProject(): array
    {
        return [
            'slug' => 'book-edition-sale',
            'label' => 'label.guided_project_book_edition_sale',
            'description' => 'description.guided_project_book_edition_sale',
            'translation_domain' => 'book',
            'order' => 6080,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_book_edition_sale_open',
                    'description' => 'description.guided_step_book_edition_sale_open',
                    'narration' => 'narration.guided_step_book_edition_sale_open',
                    'url' => $this->bookIndexUrl(),
                ],
                [
                    'label' => 'label.guided_step_book_edition_sale_edit',
                    'description' => 'description.guided_step_book_edition_sale_edit',
                    'narration' => 'narration.guided_step_book_edition_sale_edit',
                    'highlight' => '.action-edit',
                ],
                [
                    'label' => 'label.guided_step_book_edition_sale_tab',
                    'description' => 'description.guided_step_book_edition_sale_tab',
                    'narration' => 'narration.guided_step_book_edition_sale_tab',
                    'highlight' => '.form-tabs-tablist .nav-item:nth-child(2) .nav-link',
                ],
                [
                    // The editions are printed folded: the user unfolds the one to sell by its title
                    'label' => 'label.guided_step_book_edition_sale_unfold',
                    'description' => 'description.guided_step_book_edition_sale_unfold',
                    'narration' => 'narration.guided_step_book_edition_sale_unfold',
                    'highlight' => '[data-book-editions]',
                ],
                [
                    // The first slot of the open edition, its file and its price together - the slots being those its kind takes (see BookEditionFileKind::forEdition())
                    'label' => 'label.guided_step_book_edition_sale_files',
                    'description' => 'description.guided_step_book_edition_sale_files',
                    'narration' => 'narration.guided_step_book_edition_sale_files',
                    'highlight' => self::EDITION_OPEN . ' [id*="_file_"]:has(input[type="file"])',
                ],
                [
                    // The front cover's slot, which every kind of edition sold as files takes
                    'label' => 'label.guided_step_book_edition_sale_covers',
                    'description' => 'description.guided_step_book_edition_sale_covers',
                    'narration' => 'narration.guided_step_book_edition_sale_covers',
                    'highlight' => self::EDITION_OPEN . ' [id$="_file_cover_front"]:has(input[type="file"])',
                ],
                [
                    'label' => 'label.guided_step_book_edition_sale_channels',
                    'description' => 'description.guided_step_book_edition_sale_channels',
                    'narration' => 'narration.guided_step_book_edition_sale_channels',
                    'highlight' => self::EDITION_OPEN . ' [id$="_channels"]',
                ],
                [
                    'label' => 'label.guided_step_book_edition_sale_save',
                    'narration' => 'narration.guided_step_book_edition_sale_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_book_edition_sale_done',
                    'description' => 'description.guided_step_book_edition_sale_done',
                    'narration' => 'narration.guided_step_book_edition_sale_done',
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

    private function categoryIndexUrl(): string
    {
        return $this->indexUrl(BookCategoryCrudController::class);
    }

    private function serieIndexUrl(): string
    {
        return $this->indexUrl(SerieCrudController::class);
    }

    private function bookIndexUrl(): string
    {
        return $this->indexUrl(BookCrudController::class);
    }

    private function characterIndexUrl(): string
    {
        return $this->indexUrl(CharacterCrudController::class);
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
