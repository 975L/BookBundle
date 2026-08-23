<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Twig;

use c975L\BookBundle\Contract\BookCatalogProviderInterface;
use c975L\BookBundle\Service\BookCatalogRegistry;
use c975L\BookBundle\Twig\BookCatalogExtension;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

// The name the catalog page carries, which the template used to write in French - a site names it, and a translation key is honoured as readily as a plain word
class BookCatalogExtensionTest extends TestCase
{
    // A catalog nobody names keeps the bundle's own word, handed to the "book" domain
    public function testTheBundlesOwnKeyStandsWhenNothingNamesTheCatalog(): void
    {
        $this->assertSame('label.books', $this->extension()->title());
    }

    // "Les histoires" is not a key: the translator answers it as is, which is what lets a site type a word rather than declare one
    public function testAPlainWordIsPrintedAsItStands(): void
    {
        $this->assertSame('Les histoires', $this->extension(configuredTitle: 'Les histoires')->title());
    }

    public function testAProviderNamingTheCatalogWinsOverTheSetting(): void
    {
        $this->assertSame('Les albums', $this->extension('Les albums', 'Les histoires')->title());
    }

    // An empty setting is no answer at all, not an empty title
    public function testAnEmptySettingLeavesTheBundlesOwnKey(): void
    {
        $this->assertSame('label.books', $this->extension(configuredTitle: '')->title());
    }

    private function extension(?string $providedTitle = null, ?string $configuredTitle = null): BookCatalogExtension
    {
        $provider = $this->createStub(BookCatalogProviderInterface::class);
        $provider->method('getBooks')->willReturn(null);
        $provider->method('getTitle')->willReturn($providedTitle);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($configuredTitle);

        // The translator the extension asks for: it returns the key as is, which the real one does for a word that is no key
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new BookCatalogExtension(new BookCatalogRegistry([$provider], $configService), $translator);
    }
}
