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
     * @var array<string, list<array<string, mixed>>> template => one context per call, the two book variants rendering the same listing
     */
    private array $rendered = [];

    /**
     * @param list<string>                $placeholderImages
     * @param array<string, list<string>> $declaredImages    the pictures the app declares for a given row, keyed "book/<slug>" or "serie/<slug>"
     */
    private function createProvider(array $placeholderImages = ['showcase/photo-1.webp', 'showcase/photo-2.webp'], array $declaredImages = []): GalleryShowcaseProvider
    {
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            function (string $template, array $context): string {
                $this->rendered[$template][] = $context;

                return "<!-- {$template} -->";
            }
        );

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id) => $id);

        $placeholderMediaRegistry = $this->createStub(PlaceholderMediaRegistry::class);
        $placeholderMediaRegistry->method('getImages')->willReturn($placeholderImages);
        $placeholderMediaRegistry->method('getImagesFor')->willReturnCallback(static fn (string $key): array => $declaredImages[$key] ?? []);

        // The catalog is data and nothing else: the real one is handed over rather than a stub, so a made-up book renamed there is caught here
        return new GalleryShowcaseProvider($twig, $translator, $placeholderMediaRegistry, new BookSampleCatalog());
    }

    /**
     * @return list<\c975L\BookBundle\Entity\Serie>
     */
    private function renderedSeries(): array
    {
        return $this->rendered['@c975LBook/components/Serie/Series.html.twig'][0]['series'];
    }

    // The books handed to one call of a listing: the released ones and the ones still to come are two variants drawn on the very same template, so which call is asked for has to be said.
    /** @return list<\c975L\BookBundle\Entity\Book> */
    private function renderedBooks(string $template, int $call = 0): array
    {
        return $this->rendered[$template][$call]['books'];
    }

    /**
     * @return list<\c975L\BookBundle\Entity\Strip>
     */
    private function renderedStrips(): array
    {
        return $this->rendered['@c975LBook/components/Strip/Cards.html.twig'][0]['strips'];
    }

    public function testGetShowcasesReturnsAllFiveBlockKinds(): void
    {
        $showcases = $this->createProvider()->getShowcases();

        $this->assertSame(
            [
                'label.gallery_showcase_book_series',
                'label.gallery_showcase_book_categories',
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

    // A card of a serie is read for its cover: the pool is dealt by rank so a rail never repeats a photograph
    public function testEachSampleSerieCarriesItsOwnCover(): void
    {
        $this->createProvider()->getShowcases();

        $names = array_map(static fn ($serie) => $serie->getCovers()->first()->getName(), $this->renderedSeries());
        $this->assertSame(['showcase/photo-1.webp', 'showcase/photo-2.webp'], $names);
    }

    // The cover is filed as one - book_cover() reads the kind before falling back on the first image, and a rail asks it for every card
    public function testEachSampleBookCarriesACoverFiledAsSuch(): void
    {
        $this->createProvider()->getShowcases();

        // The two calls of the one listing: the books already out, then the ones still to come
        foreach ([0, 1] as $call) {
            foreach ($this->renderedBooks('@c975LBook/components/Book/Books.html.twig', $call) as $book) {
                $this->assertCount(1, $book->getCovers());
                $this->assertSame('cover', $book->getCovers()->first()->getKind());
            }
        }
    }

    // A site declaring the real cover of a made-up book is served that one, and the pool is left to the rows it declares nothing for - the very keys BookDemoFixtureProvider reads, so a demo site and the showcase show the same book
    public function testADeclaredCoverWinsOverThePool(): void
    {
        $this->createProvider(declaredImages: ['book/le-fil-rouge-1' => ['showcase/book/le-fil-rouge-1.webp']])->getShowcases();

        $names = array_map(
            static fn ($book) => $book->getCovers()->first()->getName(),
            $this->renderedBooks('@c975LBook/components/Book/Books.html.twig')
        );

        $this->assertSame('showcase/book/le-fil-rouge-1.webp', $names[0]);
        $this->assertSame('showcase/photo-2.webp', $names[1]);
    }

    // The bundled "no-cover.webp" the templates fall back on stays the answer for an app declaring nothing: a catalog with no picture to show is still a catalog
    public function testTheCardsCarryNoCoverWhenTheAppDeclaresNoPlaceholderImage(): void
    {
        $this->createProvider(placeholderImages: [])->getShowcases();

        foreach ($this->renderedSeries() as $serie) {
            $this->assertTrue($serie->getMedias()->isEmpty());
        }

        foreach ($this->renderedBooks('@c975LBook/components/Book/Books.html.twig') as $book) {
            $this->assertTrue($book->getMedias()->isEmpty());
        }
    }
}
