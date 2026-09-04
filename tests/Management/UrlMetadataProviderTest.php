<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Management;

use c975L\BookBundle\Management\UrlMetadataProvider;
use c975L\BookBundle\Routing\BookRoutePrefix;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;

class UrlMetadataProviderTest extends TestCase
{
    /**
     * @param array<string, string|null> $prefixes
     */
    private function createProvider(array $prefixes): UrlMetadataProvider
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $slug): ?string => $prefixes[$slug] ?? null);

        return new UrlMetadataProvider(new BookRoutePrefix($configService));
    }

    // The four indexes, in the words the site gave them - the pages below them are entities, each saying its own from its columns
    public function testTheFourListingsAreDeclaredUnderTheConfiguredPrefixes(): void
    {
        $provider = $this->createProvider([
            'book-route-books' => 'histoires',
            'book-route-series' => 'serie',
            'book-route-contributors' => 'auteurs',
            'book-route-strips' => 'planches',
        ]);

        $this->assertSame(['/histoires', '/serie', '/auteurs', '/planches'], $provider->getUrlMetadataPaths());
    }

    // A catalog of books and nothing else: the two families it does not serve have no url to describe, and declaring them would list a page answering 404
    public function testAFamilyTheSiteTurnedOffIsNotDeclared(): void
    {
        $provider = $this->createProvider([
            'book-route-books' => 'livres',
            'book-route-contributors' => '',
        ]);

        $this->assertSame(['/livres'], $provider->getUrlMetadataPaths());
    }

    // A site reading its books under its own routes serves no page of this bundle, and the screen stays as empty as the catalog it would describe
    public function testNothingIsDeclaredWhenNoFamilyIsServed(): void
    {
        $this->assertSame([], $this->createProvider([])->getUrlMetadataPaths());
    }
}
