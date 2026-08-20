<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Service;

use c975L\BookBundle\Service\GalleryShowcaseProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class GalleryShowcaseProviderTest extends TestCase
{
    private function createProvider(): GalleryShowcaseProvider
    {
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            static fn (string $template, array $context) => "<!-- {$template} -->"
        );

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id) => $id);

        return new GalleryShowcaseProvider($twig, $translator);
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
}
