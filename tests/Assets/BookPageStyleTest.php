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

    // One column, in source order: hero, then the summary bar, then the sections - nothing is reordered for a phone
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testThePageIsOneColumnUntilAScreenAsksForTwo(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertStringContainsString('.book-page{display:grid', $css);
        $this->assertStringContainsString('@media(min-width:1200px){.book-page{grid-template-columns:var(--toc-column-width)', $css, sprintf('"%s" no longer holds the desktop step of a book page.', $file));
    }

    // The summary only stands beside the sections it points at: the hero keeps the page's whole width
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

    // Going to the next planche is what a reader does on a strip page, and on a phone it was three scrolls away at the end of a tall image
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testThePlancheBarRestsAgainstTheBottomOfTheScreen(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertStringContainsString('.strip-bar{position:sticky;bottom:0', $css, sprintf('"%s" no longer keeps the way to the next planche on screen.', $file));
        $this->assertStringContainsString('background:var(--book-bar-background)', $css, sprintf('"%s" leaves the bar transparent, the planche then scrolling through it.', $file));
    }

    // Three links are how a planche is left at all on a phone, so a thumb has to reach them
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheBarsOwnLinksAreAsTallAsAThumbNeeds(string $file): void
    {
        $this->assertStringContainsString('min-height:44px', $this->normalize($file), sprintf('"%s" lets the planche bar fall under the 44px touch target.', $file));
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
