<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Service;

use c975L\BookBundle\Contract\BookCustomizationProviderInterface;
use c975L\BookBundle\Enum\BookMediaKind;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class BookCustomizationRegistryTest extends TestCase
{
    private static function provider(array $mediaKinds = [], array $editionKinds = [], ?string $formType = null, array $linkKinds = [], array $sections = []): BookCustomizationProviderInterface
    {
        return new readonly class ($mediaKinds, $editionKinds, $formType, $linkKinds, $sections) implements BookCustomizationProviderInterface {
            public function __construct(
                private array $mediaKinds,
                private array $editionKinds,
                private ?string $formType,
                private array $linkKinds,
                private array $sections,
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

            public function getLinkKinds(): array
            {
                return $this->linkKinds;
            }

            public function getSections(): array
            {
                return $this->sections;
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
        $registry = new BookCustomizationRegistry([self::provider()], self::registryTranslator());

        $this->assertSame(['paper', 'digital', 'audio'], array_keys($registry->getEditionKinds()));
    }

    public function testASiteNamingItsOwnEditionsReplacesTheDefaults(): void
    {
        $registry = new BookCustomizationRegistry([self::provider(editionKinds: ['illustrated_paper' => 'Illustrée papier'])], self::registryTranslator());

        $this->assertSame(['illustrated_paper' => 'Illustrée papier'], $registry->getEditionKinds());
    }

    // A site naming no file of its own gets the vocabulary the bundle sets and reads itself, which is what leaves an app with nothing to declare
    public function testTheBundlesOwnMediaKindsStandWhenNoSiteNamesAny(): void
    {
        $kinds = new BookCustomizationRegistry([self::provider()], self::registryTranslator())->getMediaKinds();

        $this->assertSame(BookMediaKind::defaults(), $kinds);
        // The ones the bundle lays itself on a dedicated field
        $this->assertArrayHasKey('cover', $kinds);
        $this->assertArrayHasKey('flipbook', $kinds);
        $this->assertArrayHasKey('extract', $kinds);
    }

    public function testASiteNamingItsOwnMediaKindsReplacesTheDefaults(): void
    {
        $registry = new BookCustomizationRegistry([
            self::provider(mediaKinds: ['page' => 'Page']),
            self::provider(mediaKinds: ['podcast' => 'Podcast']),
        ], self::registryTranslator());

        $this->assertSame(['page' => 'Page', 'podcast' => 'Podcast'], $registry->getMediaKinds());
    }

    public function testTheFirstFormTypeDeclaredIsTheOneRendered(): void
    {
        $this->assertNull(new BookCustomizationRegistry([self::provider()], self::registryTranslator())->getDataFormType());

        $registry = new BookCustomizationRegistry([
            self::provider(),
            self::provider(formType: 'App\Form\StoryDataType'),
        ], self::registryTranslator());

        $this->assertSame('App\Form\StoryDataType', $registry->getDataFormType());
    }

    // A site naming no platform of its own sells where the bundle knows how to: the stores and the podcast apps it ships icons for
    public function testTheBundlesOwnPlatformsStandWhenNoSiteNamesAny(): void
    {
        $registry = new BookCustomizationRegistry([self::provider()], self::registryTranslator());

        $this->assertSame('Kobo', $registry->getLinkLabel('epub_kobo'));
        $this->assertSame('epub', $registry->getLinkGroup('epub_kobo'));
        $this->assertSame('bundles/c975lbook/icons/kobo.svg', $registry->getLinkIcon('epub_kobo'));
    }

    // A site selling in a shop the bundle never heard of names it, and nothing of the bundle's own catalog is left standing beside it
    public function testASiteNamingItsOwnPlatformsReplacesTheDefaults(): void
    {
        $registry = new BookCustomizationRegistry([self::provider(linkKinds: [
            'epub_bookshop' => ['label' => 'Bookshop', 'group' => 'epub', 'icon' => 'images/bookshop.svg'],
        ])], self::registryTranslator());

        $this->assertSame(['epub_bookshop'], array_keys($registry->getLinkKinds()));
        $this->assertSame('Bookshop', $registry->getLinkLabel('epub_bookshop'));
        $this->assertSame('images/bookshop.svg', $registry->getLinkIcon('epub_bookshop'));
        // Fnac no longer belongs to the vocabulary: it shows as it is stored rather than under a name the site did not declare
        $this->assertSame('epub_fnac', $registry->getLinkLabel('epub_fnac'));
        $this->assertNull($registry->getLinkIcon('epub_fnac'));
    }

    // The translator the registry asks for: it returns the key as is, which the real one does for a brand - a label that is no translation key is not translated
    private static function registryTranslator(): TranslatorInterface
    {
        $translator = self::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }
}
