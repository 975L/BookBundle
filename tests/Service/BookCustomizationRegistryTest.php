<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Service;

use c975L\BookBundle\Contract\BookCustomizationProviderInterface;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use PHPUnit\Framework\TestCase;

class BookCustomizationRegistryTest extends TestCase
{
    private static function provider(array $mediaKinds = [], array $editionKinds = [], ?string $formType = null): BookCustomizationProviderInterface
    {
        return new readonly class ($mediaKinds, $editionKinds, $formType) implements BookCustomizationProviderInterface {
            public function __construct(
                private array $mediaKinds,
                private array $editionKinds,
                private ?string $formType,
            ) {
            }

            public function getMediaKinds(): array
            {
                return $this->mediaKinds;
            }

            public function getEditionKinds(): array
            {
                return $this->editionKinds;
            }

            public function getDataFormType(): ?string
            {
                return $this->formType;
            }
        };
    }

    // A site naming no edition of its own gets the paper/digital/audio a catalog holds by default, which is what leaves an app with nothing to declare
    public function testTheBundlesOwnEditionsStandWhenNoSiteNamesAny(): void
    {
        $registry = new BookCustomizationRegistry([self::provider()]);

        $this->assertSame(['paper', 'digital', 'audio'], array_keys($registry->getEditionKinds()));
    }

    public function testASiteNamingItsOwnEditionsReplacesTheDefaults(): void
    {
        $registry = new BookCustomizationRegistry([self::provider(editionKinds: ['illustrated_paper' => 'Illustrée papier'])]);

        $this->assertSame(['illustrated_paper' => 'Illustrée papier'], $registry->getEditionKinds());
    }

    // No default at all for the medias: a book media carried no kind until now, and a site naming none keeps the field out of its form
    public function testTheMediaKindsAreOnlyTheOnesDeclared(): void
    {
        $this->assertSame([], new BookCustomizationRegistry([self::provider()])->getMediaKinds());

        $registry = new BookCustomizationRegistry([
            self::provider(mediaKinds: ['page' => 'Page']),
            self::provider(mediaKinds: ['podcast' => 'Podcast']),
        ]);

        $this->assertSame(['page' => 'Page', 'podcast' => 'Podcast'], $registry->getMediaKinds());
    }

    public function testTheFirstFormTypeDeclaredIsTheOneRendered(): void
    {
        $this->assertNull(new BookCustomizationRegistry([self::provider()])->getDataFormType());

        $registry = new BookCustomizationRegistry([
            self::provider(),
            self::provider(formType: 'App\Form\StoryDataType'),
        ]);

        $this->assertSame('App\Form\StoryDataType', $registry->getDataFormType());
    }
}
