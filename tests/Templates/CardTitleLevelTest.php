<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Templates;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

// The rank of a card's title on a listing: <h3> under the head a listing block draws, <h2> when it draws none and the cards sit straight under the page's <h1> - an <h3> there skips a level, which is RGAA criterion 9.1 failing on every page opening on such a block
class CardTitleLevelTest extends TestCase
{
    // Each listing block, the function resolving its items, and the component it hands them to
    private const array BLOCKS = [
        'Books' => ['book_block_books', 'Book:Books'],
        'BooksToBePublished' => ['book_block_to_be_published', 'Book:Books'],
        'Categories' => ['book_block_categories', 'Category:Categories'],
        'Contributors' => ['book_block_contributors', 'Contributor:Contributors'],
        'Series' => ['book_block_series', 'Serie:Series'],
    ];

    // The listings carry the level down to each card and to the card closing the row
    private const array LISTINGS = [
        'Book/Books.html.twig',
        'Category/Categories.html.twig',
        'Contributor/Contributors.html.twig',
        'Serie/Series.html.twig',
    ];

    private const array CARDS = [
        'Book/Book.html.twig',
        'Category/Category.html.twig',
        'Contributor/Contributor.html.twig',
        'Serie/Serie.html.twig',
    ];

    // The case this exists for: a block with no head sits straight under the page's <h1>
    public function testABlockWithNoHeadDrawsItsCardsAsH2(): void
    {
        foreach (self::BLOCKS as $block => [$function, $component]) {
            $this->assertStringContainsString(
                'level="h2"',
                $this->component($this->renderBlock($block, $function, []), $component),
                sprintf('"%s" with no head no longer hands its cards an <h2>.', $block)
            );
        }
    }

    // A head of its own is the <h2> the cards then hang under, an eyebrow standing as that heading exactly as Section/_head.html.twig draws it
    public function testABlockCarryingAHeadDrawsItsCardsAsH3(): void
    {
        foreach (self::BLOCKS as $block => [$function, $component]) {
            $this->assertStringContainsString('level="h3"', $this->component($this->renderBlock($block, $function, ['title' => 'Nos séries']), $component), $block);
            $this->assertStringContainsString('level="h3"', $this->component($this->renderBlock($block, $function, ['eyebrow' => 'Catalogue']), $component), $block);
        }
    }

    // A listing that forgot to pass it on would leave every card at the <h3> it defaults to, the block's reading lost on the way
    public function testEachListingHandsTheLevelToItsCardsAndToItsClosingCard(): void
    {
        foreach (self::LISTINGS as $listing) {
            $template = $this->read($listing);

            $this->assertStringContainsString('level="{{ level }}"', $template, sprintf('"%s" no longer hands the level to its cards.', $listing));
            $this->assertStringContainsString('<{{ level }} class="card-title book-card__title">', $template, sprintf('"%s" hard-codes the heading of its closing card again.', $listing));
            $this->assertStringNotContainsString('<h3', $template, $listing);
        }
    }

    // Matched against the offered levels, never interpolated: block data must not be able to write a tag name. The class keeps the card's scale whatever its rank
    public function testEachCardDrawsItsTitleAtAMatchedLevel(): void
    {
        foreach (self::CARDS as $card) {
            $template = $this->read($card);

            $this->assertStringContainsString("in ['h2', 'h3', 'h4'] ? level : 'h3' %}", $template, sprintf('"%s" no longer matches its level against the offered ones.', $card));
            $this->assertStringNotContainsString('<h3', $template, sprintf('"%s" hard-codes an <h3> again.', $card));
        }

        $this->assertSame(3, substr_count($this->read('Book/Book.html.twig') . $this->read('Category/Category.html.twig') . $this->read('Contributor/Contributor.html.twig'), '<{{ level }} class="book-card__title">'));
    }

    // The planches' index opens on its cards with nothing between them and the layout's <h1>
    public function testTheStripsIndexDrawsItsSeriesAsH2(): void
    {
        $template = (string) file_get_contents(__DIR__ . '/../../templates/strip/index.html.twig');

        $this->assertMatchesRegularExpression('#<twig:c975LBook:Serie:Series [^>]*level="h2"#', $template);
    }

    // The one call to the listing component, as a bare Environment writes it out
    private function component(string $html, string $component): string
    {
        $this->assertSame(1, preg_match('#<twig:c975LBook:' . preg_quote($component, '#') . ' [^>]*/>#', $html, $matches), $html);

        return $matches[0];
    }

    private function renderBlock(string $block, string $function, array $context): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__ . '/../../templates', 'c975LBook');
        $loader->addPath(__DIR__ . '/../../vendor/c975l/core-bundle/UiBundle/templates', 'c975LUi');
        $twig = new Environment($loader);

        // Anything not empty is enough for the block to draw its listing, which is all these assertions read - a string rather than a list, the bare Environment writing the prop out as text
        $twig->addFunction(new TwigFunction($function, static fn (): string => 'item'));

        return $twig->render('@c975LBook/blocks/' . $block . '.html.twig', [...$context, 'anchor_id' => 'block-1', 'serieSlug' => 'serie']);
    }

    // Comments left out: they name the <h3> they explain, which is not a heading
    private function read(string $component): string
    {
        return (string) preg_replace('/\{#.*?#\}/s', '', (string) file_get_contents(__DIR__ . '/../../templates/components/' . $component));
    }
}
