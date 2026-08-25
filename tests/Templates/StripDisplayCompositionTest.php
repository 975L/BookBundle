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

// What a planche's page shows, and what the picture it is shared as is photographed from: a site reading its planches for their words shows the card alone, its medias being the same reply set in an image
class StripDisplayCompositionTest extends TestCase
{
    private const string DISPLAY = __DIR__ . '/../../templates/strip/display.html.twig';

    private const string CAPTURE = __DIR__ . '/../../templates/strip/card.html.twig';

    // The reply would otherwise be read twice on the same page, the second time in a picture no screen reader reaches
    public function testAPlancheReadForItsWordsShowsTheCardAndNotItsMedias(): void
    {
        $branch = $this->textBranch();

        $this->assertStringContainsString('c975LBook:Strip:Card', $branch);
        $this->assertStringNotContainsString('c975LBook:Strip:Medias', $branch, 'A planche read for its words shows the card alone: its medias repeat it as a picture.');
    }

    // A site reading its planches for their drawing - a comic strip - is untouched by the above
    public function testAPlancheReadForItsDrawingStillShowsItsMedias(): void
    {
        $this->assertStringContainsString('c975LBook:Strip:Medias', $this->drawingBranch());
    }

    // The picture is the card itself, so it can never drift from what the page shows
    public function testTheCaptureFramesTheVeryCardThePageShows(): void
    {
        $capture = $this->contents(self::CAPTURE);

        $this->assertStringContainsString('c975LBook:Strip:Card', $capture);
        $this->assertStringContainsString('strip-capture__frame', $capture);
    }

    // UiBundle's layout and its body written whole: a navbar, a footer or a cookie banner would be photographed along with the card
    public function testTheCaptureCarriesNothingButTheCard(): void
    {
        $capture = $this->contents(self::CAPTURE);

        $this->assertStringContainsString("{% extends '@c975LUi/layout.html.twig' %}", $capture);
        $this->assertStringContainsString('{% block body %}', $capture);
    }

    // What the page shows when the site reads a planche for its words
    private function textBranch(): string
    {
        $display = $this->contents(self::DISPLAY);

        $this->assertSame(1, preg_match('/\{%\s*if textFirst\s*%\}(.+?)\{%\s*else\s*%\}/s', $display, $matches), 'The page no longer parts its two ways of showing a planche - check this test still says what it means.');

        return $matches[1];
    }

    // And what it shows otherwise - everything past the fork, the branch carrying conditions of its own that no expression here would count reliably
    private function drawingBranch(): string
    {
        $display = $this->contents(self::DISPLAY);

        $this->assertSame(1, preg_match('/\{%\s*if textFirst\s*%\}.+?\{%\s*else\s*%\}/s', $display, $matches));

        return substr($display, (int) strpos($display, $matches[0]) + strlen($matches[0]));
    }

    private function contents(string $path): string
    {
        $this->assertFileExists($path);
        $contents = file_get_contents($path);
        $this->assertIsString($contents);

        return $contents;
    }
}
