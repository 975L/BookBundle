<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Service;

use c975L\BookBundle\Service\BookSampleCatalog;
use c975L\BookBundle\Service\GalleryShowcaseProvider;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class GalleryShowcaseProviderTest extends TestCase
{
    /**
     * @var array<string, array<string, mixed>> template => the context it was rendered with
     */
    private array $rendered = [];

    /**
     * @param list<string> $placeholderImages
     */
    private function createProvider(array $placeholderImages = ['showcase/photo-1.webp', 'showcase/photo-2.webp']): GalleryShowcaseProvider
    {
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            function (string $template, array $context): string {
                $this->rendered[$template] = $context;

                return "<!-- {$template} -->";
            }
        );

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id) => $id);

        $placeholderMediaRegistry = $this->createStub(PlaceholderMediaRegistry::class);
        $placeholderMediaRegistry->method('getImages')->willReturn($placeholderImages);

        // The catalog is data and nothing else: the real one is handed over rather than a stub, so a made-up book renamed there is caught here
        return new GalleryShowcaseProvider($twig, $translator, $placeholderMediaRegistry, new BookSampleCatalog());
    }

    /**
     * @return list<\c975L\BookBundle\Entity\Strip>
     */
    private function renderedStrips(): array
    {
        return $this->rendered['@c975LBook/components/Strip/Cards.html.twig']['strips'];
    }

    public function testGetShowcasesReturnsAllFourBlockKinds(): void
    {
        $showcases = $this->createProvider()->getShowcases();

        $this->assertSame(
            [
                'label.gallery_showcase_book_series',
                'label.gallery_showcase_book_books',
                'label.gallery_showcase_book_to_be_published',
                'label.gallery_showcase_book_serie_strips',
            ],
            array_keys($showcases)
        );
    }

    // Every showcase is single-variant (no style choice to compare, unlike alert/button in UiBundle)
    public function testEveryShowcaseHasASingleUnlabelledVariant(): void
    {
        $showcases = $this->createProvider()->getShowcases();

        foreach ($showcases as $label => $showcase) {
            $this->assertSame([''], array_keys($showcase['variants']), "Showcase \"{$label}\" should have a single unlabelled variant");
        }
    }

    // Each showcase stands in for its own block kind - the gallery suppresses that kind's own regular (empty) preview card once "kind" is set here, so none of them shows up twice
    public function testEveryShowcaseStandsInForItsOwnBlockKind(): void
    {
        $showcases = $this->createProvider()->getShowcases();

        $this->assertSame('book_series', $showcases['label.gallery_showcase_book_series']['kind']);
        $this->assertSame('book_books', $showcases['label.gallery_showcase_book_books']['kind']);
        $this->assertSame('book_to_be_published', $showcases['label.gallery_showcase_book_to_be_published']['kind']);
        $this->assertSame('book_serie_strips', $showcases['label.gallery_showcase_book_serie_strips']['kind']);
    }

    // Each sample planche carries its own drawing, the declared pool being rotated so the three don't share one
    public function testEachSampleStripCarriesItsOwnPlaceholderImage(): void
    {
        $this->createProvider()->getShowcases();

        $names = array_map(static fn ($strip) => $strip->getMedias()->first()->getName(), $this->renderedStrips());
        $this->assertSame(['showcase/photo-1.webp', 'showcase/photo-2.webp', 'showcase/photo-1.webp'], $names);
    }

    // An app declaring no placeholder image gets the bare cards it got before, rather than three broken frames
    public function testTheStripsCarryNoMediaWhenTheAppDeclaresNoPlaceholderImage(): void
    {
        $this->createProvider(placeholderImages: [])->getShowcases();

        foreach ($this->renderedStrips() as $strip) {
            $this->assertTrue($strip->getMedias()->isEmpty());
        }
    }
}
