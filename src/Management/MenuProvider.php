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
use c975L\BookBundle\Controller\Management\ContributorCrudController;
use c975L\BookBundle\Controller\Management\SerieCrudController;
use c975L\BookBundle\Controller\Management\StripCrudController;
use c975L\ConfigBundle\Management\MenuProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;

class MenuProvider implements MenuProviderInterface
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    public function getMenuSection(): array
    {
        return [
            'label' => 'label.catalog',
            'translation_domain' => 'book',
        ];
    }

    public function getMenus(): array
    {
        return [
            'serie' => [
                'controller' => SerieCrudController::class,
                'label' => 'label.series',
                'narration' => 'narration.series',
                'translation_domain' => 'book',
                'icon' => 'fas fa-layer-group',
                // The very text the series screen opens on (see serie_crud_index.html.twig), reused as-is for the onboarding tour rather than written again for it
                'description' => 'label.info_serie',
                // The bar SerieCrudController states on its own rows - a catalog is written by whoever writes the site
                'role' => $this->configService->get('site-role-editor'),
            ],
            'book' => [
                'controller' => BookCrudController::class,
                'label' => 'label.books',
                'narration' => 'narration.books',
                'translation_domain' => 'book',
                'icon' => 'fas fa-book',
                // The very text the catalog screen opens on (see book_crud_index.html.twig), reused as-is for the onboarding tour rather than written again for it
                'description' => 'label.info_book',
                // The bar BookCrudController states on its own rows
                'role' => $this->configService->get('site-role-editor'),
            ],
            'category' => [
                'controller' => BookCategoryCrudController::class,
                'label' => 'label.categories',
                'narration' => 'narration.categories',
                'translation_domain' => 'book',
                'icon' => 'fas fa-tags',
                // The very text the categories screen opens on (see category_crud_index.html.twig), reused as-is for the onboarding tour rather than written again for it
                'description' => 'label.info_category',
                // The bar BookCategoryCrudController states on its own rows - a catalog is written by whoever writes the site
                'role' => $this->configService->get('site-role-editor'),
            ],
            'contributor' => [
                'controller' => ContributorCrudController::class,
                'label' => 'label.contributors',
                'narration' => 'narration.contributors',
                'translation_domain' => 'book',
                'icon' => 'fas fa-user-pen',
                // The very text the people screen opens on (see contributor_crud_index.html.twig), reused as-is for the onboarding tour rather than written again for it
                'description' => 'label.info_contributor',
                // The bar ContributorCrudController states on its own rows - a catalog is written by whoever writes the site
                'role' => $this->configService->get('site-role-editor'),
            ],
            'strip' => [
                'controller' => StripCrudController::class,
                'label' => 'label.strips',
                'narration' => 'narration.strips',
                'translation_domain' => 'book',
                'icon' => 'fas fa-border-all',
                // The very text the strips screen opens on (see strip_crud_index.html.twig), reused as-is for the onboarding tour rather than written again for it
                'description' => 'label.info_strip',
                // The bar StripCrudController states on its own rows
                'role' => $this->configService->get('site-role-editor'),
            ],
        ];
    }

    public function getLinks(): array
    {
        return [];
    }
}
