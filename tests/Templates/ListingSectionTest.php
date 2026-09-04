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

// The head every listing kind of this bundle draws its grid under: what it writes with a title typed on the block, and what it writes with none
class ListingSectionTest extends TestCase
{
    private const string TEMPLATE = '@c975LBook/components/Listing/Section.html.twig';

    // A typed head is a section: its eyebrow over its title, its paragraph under them, and the anchor the block computed
    public function testATypedHeadIsRenderedAsASection(): void
    {
        $html = $this->render([
            'eyebrow' => 'Catalogue',
            'title' => 'Nos séries',
            'text' => '<div>Ce que nous publions.</div>',
            'background' => 'primary',
            'id' => 'nos-series-12',
        ]);

        $this->assertStringContainsString('<section class="book-listing section--bg-primary" id="nos-series-12">', $html);
        $this->assertStringContainsString('<p class="section-eyebrow">Catalogue</p>', $html);
        $this->assertStringContainsString('<h2 class="section-title">Nos séries</h2>', $html);
        // The paragraph is written in the rich text editor, so it is printed as the html it is rather than escaped
        $this->assertStringContainsString('<div class="book-listing__intro"><div>Ce que nous publions.</div></div>', $html);
    }

    // A page composed before those fields existed types none of them: the grid is drawn bare, and a <section> with no heading in it would be invalid html
    public function testAListingWithNoHeadIsRenderedAsADiv(): void
    {
        $html = $this->render([]);

        $this->assertStringContainsString('<div class="book-listing">', $html);
        $this->assertStringNotContainsString('<section', $html);
        $this->assertStringNotContainsString('section-head', $html);
        $this->assertStringNotContainsString('book-listing__intro', $html);
    }

    // The value comes from stored block data: only the three the sass paints are written, anything else painting no flat at all
    public function testOnlyADeclaredBackgroundWritesItsClass(): void
    {
        $this->assertStringContainsString('class="book-listing"', $this->render(['title' => 'Nos séries', 'background' => 'onclick=alert(1)']));
    }

    /**
     * @param array<string, string> $context
     */
    private function render(array $context): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__ . '/../../templates', 'c975LBook');
        $loader->addPath(__DIR__ . '/../../vendor/c975l/core-bundle/UiBundle/templates', 'c975LUi');

        return new Environment($loader)->render(self::TEMPLATE, $context);
    }
}
