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
use c975L\ConfigBundle\Management\AbstractMenuProvider;

class MenuProvider extends AbstractMenuProvider
{
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
                'icon' => 'fas fa-book',
            ],
            'book' => [
                'controller' => BookCrudController::class,
                'label' => 'label.books',
                'translation_domain' => 'book',
                'icon' => 'fas fa-book',
            ],
        ];
    }
}
