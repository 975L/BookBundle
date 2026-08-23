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
                'translation_domain' => 'book',
                'icon' => 'fas fa-book',
                // The very text the catalog screen opens on (see book_crud_index.html.twig), reused as-is for the onboarding tour rather than written again for it
                'description' => 'label.info_book',
                // The bar BookCrudController states on its own rows
                'role' => $this->configService->get('site-role-editor'),
            ],
            'strip' => [
                'controller' => StripCrudController::class,
                'label' => 'label.strips',
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
