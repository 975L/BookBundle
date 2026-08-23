<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Management;

use c975L\BookBundle\Controller\Management\BookCrudController;
use c975L\BookBundle\Controller\Management\SerieCrudController;
use c975L\BookBundle\Controller\Management\StripCrudController;
use c975L\BookBundle\Management\MenuProvider;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;

class MenuProviderTest extends TestCase
{
    // A section of its own rather than the shared "Gestion" one: a catalog is what the site is about, not how it is run
    public function testTheSectionIsThisBundlesOwnCatalogOne(): void
    {
        $this->assertSame(
            ['label' => 'label.catalog', 'translation_domain' => 'book'],
            $this->createProvider()->getMenuSection(),
        );
    }

    public function testItContributesTheThreeScreensOfTheCatalog(): void
    {
        $menus = $this->createProvider()->getMenus();

        $this->assertSame(SerieCrudController::class, $menus['serie']['controller']);
        $this->assertSame(BookCrudController::class, $menus['book']['controller']);
        $this->assertSame(StripCrudController::class, $menus['strip']['controller']);
    }

    // A catalog is written by whoever writes the site: without the key an entry takes the admin default and goes missing from an editor's sidebar, with the tour step that walks to it (see MenuProviderInterface::getMenus())
    public function testEveryEntryNamesTheEditorBarItsOwnScreenStates(): void
    {
        $menus = $this->createProvider()->getMenus();

        foreach (['serie', 'book', 'strip'] as $slug) {
            $this->assertSame('ROLE_EDITOR', $menus[$slug]['role'], sprintf('The "%s" entry does not name the bar its own crud states', $slug));
        }
    }

    public function testItContributesNoLink(): void
    {
        $this->assertSame([], $this->createProvider()->getLinks());
    }

    // Answers the editor key each entry names, the bar its own screen states
    private function createProvider(): MenuProvider
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(
            static fn (string $key) => 'site-role-editor' === $key ? 'ROLE_EDITOR' : null
        );

        return new MenuProvider($configService);
    }
}
