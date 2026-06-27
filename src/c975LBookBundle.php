<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle;

use c975L\UiBundle\Namer\UiMediaNamer;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class c975LBookBundle extends AbstractBundle
{
    public function prependExtension(ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        if (!$container->hasExtension('vich_uploader')) {
            return;
        }

        $container->prependExtensionConfig('vich_uploader', [
            'mappings' => [
                'books' => [
                    'uri_prefix' => '',
                    'upload_destination' => '%kernel.project_dir%/public/medias/book/books/',
                    'namer' => UiMediaNamer::class,
                    'inject_on_load' => false,
                    'delete_on_update' => true,
                    'delete_on_remove' => true,
                ],
                'series' => [
                    'uri_prefix' => '',
                    'upload_destination' => '%kernel.project_dir%/public/medias/book/series/',
                    'namer' => UiMediaNamer::class,
                    'inject_on_load' => false,
                    'delete_on_update' => true,
                    'delete_on_remove' => true,
                ],
            ],
        ]);
    }

    public function loadExtension(array $config, ContainerConfigurator $containerConfigurator, ContainerBuilder $containerBuilder): void
    {
        $containerConfigurator->import('../config/services.yaml');
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
