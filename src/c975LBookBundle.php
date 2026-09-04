<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle;

use c975L\BookBundle\Contract\BookCatalogProviderInterface;
use c975L\BookBundle\Contract\BookCustomizationProviderInterface;
use c975L\ConfigBundle\DependencyInjection\Compiler\TaggedInterfacePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class c975LBookBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $containerConfigurator, ContainerBuilder $containerBuilder): void
    {
        $containerConfigurator->import('../config/services.yaml');
    }

    // The bundle's own Stimulus controllers, which importmap.php names as an entrypoint - a path the app cannot declare for it, the bundle living under vendor/ - and the limiter its one public form is served under
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('framework', [
            'asset_mapper' => [
                'paths' => [
                    __DIR__ . '/../assets' => '@c975l/book-bundle',
                ],
            ],
            'rate_limiter' => [
                'book_release_alert' => [
                    'policy' => 'sliding_window',
                    'limit' => 10,
                    'interval' => '1 hour',
                ],
            ],
        ]);
    }

    // Collects what each site declares about its own catalog, so BookCustomizationRegistry reads them all without the app having to tag its provider by hand
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new TaggedInterfacePass(BookCustomizationProviderInterface::class, 'book.customization_provider'));
        $container->addCompilerPass(new TaggedInterfacePass(BookCatalogProviderInterface::class, 'book.catalog_provider'));
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
