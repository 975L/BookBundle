<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Twig;

use c975L\BookBundle\Controller\Management\BookCrudController;
use c975L\BookBundle\Controller\Management\ContributorCrudController;
use c975L\BookBundle\Controller\Management\SerieCrudController;
use c975L\BookBundle\Controller\Management\StripCrudController;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

// Where each section of a display page is edited, for the hover pencil UiBundle floats over an element carrying "data-block-edit-url" (see its assets/js/block-edit-overlay.js). The "focusField" query param is what opens the right tab and scrolls to the right field once on the edit screen (see UiBundle's assets/js/field-focus.js), instead of dropping the editor at the top of a form holding dozens of fields
class BookEditUrlExtension
{
    // Anchor of the section on the public page => property of the form it is written in. A section whose anchor is absent here gets no pencil at all, which is what a section pointing at no field of its own deserves - a serie's list of books is written on each book's own screen, not on the serie's
    private const array BOOK_FIELDS = [
        'hero' => 'title',
        'resume' => 'summary',
        'apercu' => 'videos',
        // The extracts are the files held under no edition, the editions are the files held under one, and the shops and the podcasts are the platforms each edition is sold on: all four are edited inside the editions collection
        'extracts' => 'editions',
        'editions' => 'editions',
        'shops' => 'editions',
        'podcasts' => 'editions',
        'crowdfunding' => 'crowdfunding',
        'presse' => 'presses',
        'marketing' => 'marketings',
        'informations' => 'published',
    ];

    private const array SERIE_FIELDS = [
        'hero' => 'title',
        'resume' => 'summary',
    ];

    // A person's page is their portrait, what they are introduced with, and what they signed - the last being written on each book's own screen, it gets no pencil
    private const array CONTRIBUTOR_FIELDS = [
        'hero' => 'name',
        'resume' => 'summary',
    ];

    private const array STRIP_FIELDS = [
        'hero' => 'title',
        'medias' => 'medias',
        'summary' => 'summary',
        'characters' => 'characters',
        'sourceUrl' => 'sourceUrl',
    ];

    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly BookCustomizationRegistry $customizationRegistry,
    ) {
    }

    /** @return array<string, string> */
    #[AsTwigFunction('book_edit_urls')]
    public function book(Book $book): array
    {
        return $this->urls(BookCrudController::class, $book->getId(), array_merge(self::BOOK_FIELDS, $this->declaredFields()));
    }

    // The field a section the site added is written in, which is what gives it the same pencil as the bundle's own (see BookCustomizationProviderInterface::getSections()). Its own fields being held in one JSON payload, "data" is what such a section names most of the time
    /** @return array<string, string> */
    private function declaredFields(): array
    {
        $fields = [];

        foreach ($this->customizationRegistry->getSections() as $key => $declaration) {
            if (\is_string($declaration['field'] ?? null) && '' !== $declaration['field']) {
                $fields[$key] = $declaration['field'];
            }
        }

        return $fields;
    }

    /** @return array<string, string> */
    #[AsTwigFunction('serie_edit_urls')]
    public function serie(Serie $serie): array
    {
        return $this->urls(SerieCrudController::class, $serie->getId(), self::SERIE_FIELDS);
    }

    /** @return array<string, string> */
    #[AsTwigFunction('contributor_edit_urls')]
    public function contributor(Contributor $contributor): array
    {
        return $this->urls(ContributorCrudController::class, $contributor->getId(), self::CONTRIBUTOR_FIELDS);
    }

    /** @return array<string, string> */
    #[AsTwigFunction('strip_edit_urls')]
    public function strip(Strip $strip): array
    {
        return $this->urls(StripCrudController::class, $strip->getId(), self::STRIP_FIELDS);
    }

    // The whole screen rather than one of its fields: a card in a listing stands for the row entire, where a section of its own page stands for the field it is printed from
    #[AsTwigFunction('strip_edit_url')]
    public function stripEditUrl(Strip $strip): ?string
    {
        return $this->editUrl(StripCrudController::class, $strip->getId());
    }

    // The same, for the card a book is reached by
    #[AsTwigFunction('book_edit_url')]
    public function bookEditUrl(Book $book): ?string
    {
        return $this->editUrl(BookCrudController::class, $book->getId());
    }

    // And for a serie's, which its own screen edits whole: a serie has no page cut into sections, it has a summary and the books it holds
    #[AsTwigFunction('serie_edit_url')]
    public function serieEditUrl(Serie $serie): ?string
    {
        return $this->editUrl(SerieCrudController::class, $serie->getId());
    }

    // And for the card a person is reached by, which their own screen edits whole
    #[AsTwigFunction('contributor_edit_url')]
    public function contributorEditUrl(Contributor $contributor): ?string
    {
        return $this->editUrl(ContributorCrudController::class, $contributor->getId());
    }

    private function editUrl(string $crudControllerFqcn, ?int $entityId): ?string
    {
        // A row with no id has no screen to point at - an in-memory one, a fixture preview
        if (null === $entityId) {
            return null;
        }

        // Swallowed for the same reason as urls() below
        try {
            return $this->adminUrlGenerator
                ->unsetAll()
                ->setController($crudControllerFqcn)
                ->setAction(Action::EDIT)
                ->setEntityId($entityId)
                ->generateUrl()
            ;
        } catch (\Throwable) {
            return null;
        }
    }

    // A failure to build them is swallowed rather than left to throw: EasyAdmin resolves the dashboard these URLs are mounted under through a cache map written only when the route collection is regenerated (see AdminRouteGenerator::saveAdminRoutesInCache()), so that pool being emptied while the compiled routes stay fresh makes every generateUrl() call from a public page throw, and it stays that way until the routes are regenerated. Losing the editor's hover buttons beats 500ing the page for the only people able to fix it
    /**
     * @param array<string, string> $fields
     *
     * @return array<string, string>
     */
    private function urls(string $crudControllerFqcn, ?int $entityId, array $fields): array
    {
        if (null === $entityId) {
            return [];
        }

        $urls = [];

        try {
            foreach ($fields as $anchor => $field) {
                $urls[$anchor] = $this->adminUrlGenerator
                    ->unsetAll()
                    ->setController($crudControllerFqcn)
                    ->setAction(Action::EDIT)
                    ->setEntityId($entityId)
                    ->set('focusField', $field)
                    ->generateUrl()
                ;
            }
        } catch (\Throwable) {
            return [];
        }

        return $urls;
    }
}
