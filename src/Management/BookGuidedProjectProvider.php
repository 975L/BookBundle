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
use c975L\BookBundle\Controller\Management\SerieCrudController;
use c975L\ConfigBundle\Management\GuidedProjectProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

// This bundle's guided projects, continuing the order sequence after ConfigBundle (10-40), SiteBundle (50-80), UiBundle (90-110), SocialBundle (120-137) and GalleryBundle (140-160). They follow the order a catalog is actually built in - the serie holds the books, so it comes first. Only the opening step of each carries an url: from there the parcours walks the screen the user has been sent to, highlighting the button or the field they are meant to use next (see ConfigBundle's assets/js/guided-project.js)
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
            $this->serieCreationProject(),
            $this->bookCreationProject(),
            $this->bookCompositionProject(),
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
            'order' => 170,
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
                    'label' => 'label.guided_step_book_serie_creation_kind',
                    'description' => 'description.guided_step_book_serie_creation_kind',
                    'highlight' => '#Serie_kind',
                ],
                [
                    'label' => 'label.guided_step_book_serie_creation_summary',
                    'description' => 'description.guided_step_book_serie_creation_summary',
                    'highlight' => '#Serie_summary',
                ],
                [
                    'label' => 'label.guided_step_book_serie_creation_covers',
                    'description' => 'description.guided_step_book_serie_creation_covers',
                    'highlight' => '#Serie_covers',
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
            'order' => 180,
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
                    'highlight' => '#Book_serie',
                ],
                [
                    'label' => 'label.guided_step_book_creation_published',
                    'description' => 'description.guided_step_book_creation_published',
                    'highlight' => '#Book_published',
                ],
                [
                    // The second tab of the form, an ISBN belonging to an edition and no longer to the book itself (see BookCrudController)
                    'label' => 'label.guided_step_book_creation_technical_tab',
                    'description' => 'description.guided_step_book_creation_technical_tab',
                    'highlight' => '.form-tabs-tablist .nav-item:nth-child(2) .nav-link',
                ],
                [
                    // The editions are listed here, not edited: the block prints no field id, so the row's own marker is what the tour points at (see BookCrudController)
                    'label' => 'label.guided_step_book_creation_editions',
                    'description' => 'description.guided_step_book_creation_editions',
                    'highlight' => '[data-book-editions]',
                ],
                [
                    'label' => 'label.guided_step_book_creation_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    // Back on the catalog, where each book leads into its own editions - the files and the platforms are added there, one edition at a time (see BookEditionCrudController)
                    'label' => 'label.guided_step_book_creation_editions_add',
                    'description' => 'description.guided_step_book_creation_editions_add',
                    'highlight' => '.action-editions',
                ],
                [
                    'label' => 'label.guided_step_book_creation_done',
                    'description' => 'description.guided_step_book_creation_done',
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
            'order' => 190,
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
                    'label' => 'label.guided_step_book_composition_add',
                    'description' => 'description.guided_step_book_composition_add',
                    'highlight' => '[data-block-collection]',
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

    // The role every catalog screen sits behind, the same ConfigBundle entry its controllers read (see BookCrudController) - a parcours walking screens the user can't open reads as a broken one
    private function roleNeeded(): string
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

    private function indexUrl(string $controller): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController($controller)
            ->setAction(Action::INDEX)
            ->generateUrl();
    }
}
