<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Assets;

use PHPUnit\Framework\TestCase;

// Mobile first: a phone gets one column and reads no query at all, the wider steps being what a screen adds rather than what a phone has to undo
class BookPageStyleTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function stylesheetProvider(): array
    {
        return [
            'styles.css' => ['styles.css'],
            'styles.min.css' => ['styles.min.css'],
        ];
    }

    // One column, in source order: hero, then the summary bar, then the sections - nothing is reordered for a phone. The second column is only opened on a page carrying a summary of anchors: a planche and a serie have none, and were laying their whole content in the column that summary would have stood in
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testThePageIsOneColumnUntilASummaryAsksForTwo(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertStringContainsString('.book-page{display:grid', $css);
        $this->assertStringContainsString('@media(min-width:1200px){.book-page:has(>.book-page__toc){grid-template-columns:var(--toc-column-width)', $css, sprintf('"%s" no longer holds the desktop step of a book page.', $file));
    }

    // The summary only stands beside the sections it points at: the hero keeps the page's whole width, and the lead under it too - it is the sentence that hero opens on, not one of the sections
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheHeroKeepsTheWholeWidthOnceTheSummaryTakesAColumn(string $file): void
    {
        $this->assertStringContainsString('.book-page__hero{grid-column:1/-1', $this->normalize($file));
    }

    // A cover is shown whole: its own proportions are part of what is being shown, unlike the page images of an illustrated album
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testACoverIsNeverCroppedToFillItsBox(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertStringContainsString('.book-hero__cover{', $css);
        $this->assertStringContainsString('height:auto', $css);
        $this->assertStringNotContainsString('.book-hero__cover{object-fit:cover', $css, sprintf('"%s" crops the cover to fill its box.', $file));
    }

    // Going to the next planche is what a reader does on a strip page: the arrows are laid over what the page shows, where the eye already is
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheArrowsAreLaidOverThePlancheTheyBrowse(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertStringContainsString('.strip-viewer{position:relative', $css, sprintf('"%s" no longer holds the arrows against the planche they browse.', $file));
        $this->assertStringContainsString('.strip-nav{position:absolute', $css, sprintf('"%s" drops the arrows back into the flow, under the planche.', $file));
        $this->assertStringContainsString('.strip-nav--previous{left:var(--book-strip-nav-offset)', $css);
        $this->assertStringContainsString('.strip-nav--next{right:var(--book-strip-nav-offset)', $css);
    }

    // A touch screen has no hover to reveal them with, so hiding them at rest is held inside "hover: hover" - on a phone they simply stay on, or a planche could not be left at all
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheArrowsOnlyFadeOutWhereThereIsAPointerToBringThemBack(string $file): void
    {
        $css = $this->normalize($file);
        $faded = strpos($css, '.strip-nav{opacity:0;pointer-events:none');

        $this->assertNotFalse($faded, sprintf('"%s" no longer keeps the arrows out of the way until the visitor points at the planche.', $file));
        $this->assertStringContainsString('@media(hover:hover){', substr($css, 0, $faded), sprintf('"%s" hides the arrows on a touch screen, which has no hover to bring them back with.', $file));
    }

    // A platform's mark is drawn in the brand's colors on a light ground: without a plate of its own it is read against whatever the site happens to be, and a dark one swallows it
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testAPlatformIsReachedThroughATileCarryingItsOwnGround(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertStringContainsString('background:var(--book-link-tile-background)', $css, sprintf('"%s" leaves a platform\'s mark on the page\'s own ground.', $file));
        $this->assertStringContainsString('border-radius:var(--book-link-tile-radius)', $css, sprintf('"%s" no longer rounds the platform tiles.', $file));
    }

    // The tiles of one card are one size, whatever the shape the mark inside was drawn in and however long the platform's name is
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testEveryTileOfACardIsTheSameSize(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertStringContainsString('width:var(--book-link-tile-width)', $css);
        $this->assertStringContainsString('height:var(--book-link-tile-height)', $css);
        $this->assertStringContainsString('height:var(--book-link-tile-icon-size);object-fit:contain', $css, sprintf('"%s" lets a mark keep its own height, which makes two tiles of two sizes.', $file));
        $this->assertStringContainsString('max-height:calc(var(--book-link-tile-label-lines)*1.2em)', $css, sprintf('"%s" gives a long name the room it asks for, which raises its whole row.', $file));
    }

    // Not one color of its own: the ground and the ink are the site's, which the "theme" config group holds, so a book page follows the site it is on
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testThePagesHardcodeNoColourOfTheirOwn(string $file): void
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($this->path($file)));
        // The :root block is where the fallbacks of the tokens themselves live, and a fallback is what makes the sheet hold up without the site
        $css = (string) preg_replace('/:root\s*\{.*?\n?\}/s', '', $css);

        $this->assertDoesNotMatchRegularExpression('/#[0-9a-f]{3,8}\b|rgba?\(/i', $css, sprintf('"%s" paints a book page in a color no admin can reach.', $file));
    }

    private function path(string $file): string
    {
        $path = \dirname(__DIR__, 2) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        return $path;
    }

    // Strips comments and collapses whitespace, so the same assertions hold on the minified sheet
    private function normalize(string $file): string
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($this->path($file)));

        return (string) preg_replace('/\s+/', '', $css);
    }
}
