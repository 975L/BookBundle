<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Assets;

use c975L\BookBundle\Twig\BookSectionsExtension;
use PHPUnit\Framework\TestCase;

// BookSectionsExtension names the sections and the display templates render them: nothing checks the two agree at runtime, and a summary entry pointing at an id no template writes is a link that scrolls nowhere - silently
class SectionAnchorsTest extends TestCase
{
    public function testEverySectionNamedByTheExtensionIsRenderedBySomeTemplate(): void
    {
        $templates = $this->templates();
        // The book page routes each key to the component printing it, the serie page still naming its two cards itself. Read by its own path: both pages are a "display.html.twig", and one would stand for the other
        $routed = (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/book/display.html.twig');
        $orphans = [];

        foreach ($this->anchors() as $anchor) {
            $isRouted = str_contains($routed, sprintf("'%s': '", $anchor));
            $isWritten = [] !== array_filter($templates, static fn (string $twig): bool => str_contains($twig, sprintf('id="%s"', $anchor)));

            if (!$isRouted && !$isWritten) {
                $orphans[] = $anchor;
            }
        }

        $this->assertSame([], $orphans, sprintf(
            'BookSectionsExtension offers %s, which the page routes to no component and no template writes as an id: the summary entry scrolls nowhere.',
            implode(', ', $orphans)
        ));
    }

    // Each component defaults the anchor and the name of its own section, which is what a caller handing no section of its own gets - and what the site's declaration is laid over
    public function testEachSectionComponentDefaultsItsOwnAnchor(): void
    {
        $templates = $this->templates();

        // Resume is not there: the summary is no section, it is the sentence the book opens on, laid under the hero with neither title nor anchor. Nor is the serie: it holds one line of the book's informations (see Book:Informations)
        foreach (['Videos.html.twig' => 'apercu', 'Extracts.html.twig' => 'extracts', 'Presses.html.twig' => 'presse', 'Marketings.html.twig' => 'marketing', 'Informations.html.twig' => 'informations', 'Shops.html.twig' => 'shops', 'Podcasts.html.twig' => 'podcasts', 'Crowdfunding.html.twig' => 'crowdfunding'] as $file => $anchor) {
            $this->assertStringContainsString(
                sprintf("{anchor: '%s'", $anchor),
                $templates[$file],
                sprintf('%s no longer defaults its own anchor, so a caller handing no section prints a card with no id.', $file)
            );
        }
    }

    // The room a section leaves above itself so the resting summary bar does not cover its own title is UiBundle's "toc-target" - a section given an id but not the class lands under the bar after a jump. A card of platform buttons hands its id to Book:Links, which is where its own class is
    public function testEveryTemplateWritingOneOfThoseIdsAlsoLeavesRoomAboveIt(): void
    {
        $templates = $this->templates();
        $missing = [];

        // Every section of a book's page prints through the one embedded card, which is the single place that class now has to be
        $this->assertStringContainsString('toc-target', $this->sectionFragment(), 'The embedded card no longer leaves room above itself, so every section of the page lands under the bar.');

        foreach ($this->anchors() as $anchor) {
            foreach ($templates as $file => $twig) {
                if (!str_contains($twig, sprintf('id="%s"', $anchor))) {
                    continue;
                }

                if (!str_contains($twig, 'toc-target') && !str_contains($twig, '_section.html.twig')) {
                    $missing[] = sprintf('%s (%s)', $anchor, $file);
                }
            }
        }

        $this->assertSame([], $missing, sprintf(
            "A section is given an anchor without the class leaving room above it, so a jump lands it under the bar:\n- %s",
            implode("\n- ", $missing)
        ));
    }

    // The display pages read the list once and render from it: a component called outside that guard would print a section nothing on the page leads to
    public function testTheDisplayPagesRenderTheirSectionsFromTheListTheyOffer(): void
    {
        // Neither page carries a summary of anchors any more: the hero is handed the very list the sections are rendered from, a bar of chips overflowing a phone where a row of buttons wraps
        foreach (['book' => ['book_sections(book)', 'sections="{{ sections }}"'], 'serie' => ['serie_sections(serie)', 'sections="{{ sections }}"']] as $page => [$call, $handedOver]) {
            $twig = (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/' . $page . '/display.html.twig');

            $this->assertStringContainsString('{% set sections = ' . $call . ' %}', $twig, sprintf('The %s page no longer reads its sections from the extension.', $page));
            $this->assertStringContainsString($handedOver, $twig, sprintf('The %s page leads to something other than the list it renders.', $page));
        }
    }

    /**
     * The anchors BookSectionsExtension can name, read off its source rather than restated here.
     *
     * @return array<int, string>
     */
    private function anchors(): array
    {
        $php = (string) file_get_contents(\dirname(__DIR__, 2) . '/src/Twig/BookSectionsExtension.php');

        preg_match_all("/'([a-z]+)' => \['label\./", $php, $matches);

        $this->assertNotEmpty($matches[1], 'No section was read at all, this test guards nothing.');

        return array_values(array_unique($matches[1]));
    }

    // The contents of a card travel as an already-rendered string, never as a block the caller overrides: a block declared around a component's slot never reaches the inside of it, and the cards came out titled and empty - which the Twig lint and every test reading a template alike saw as perfectly fine
    public function testEachSectionHandsItsContentsOverAsAString(): void
    {
        $this->assertStringContainsString("{{ body|default('')|raw }}", $this->sectionFragment(), 'The card no longer prints the contents it was handed, so every section of the page comes out empty.');
        $this->assertStringNotContainsString('{% block body %}', $this->sectionFragment());

        foreach ($this->templates() as $file => $twig) {
            if (!str_contains($twig, '_section.html.twig')) {
                continue;
            }

            $this->assertStringContainsString('{% set body %}', $twig, sprintf('%s no longer captures its own contents.', $file));
            $this->assertStringContainsString('body: body', $twig, sprintf('%s captures its contents without handing them to the card.', $file));
        }
    }

    // The card every section of a book's page is printed in, embedded rather than a component (see templates/book/_section.html.twig)
    private function sectionFragment(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/book/_section.html.twig');
    }

    /**
     * @return array<string, string>
     */
    private function templates(): array
    {
        $templates = [];

        foreach (['/templates/components/Book/*.twig', '/templates/book/*.twig', '/templates/serie/*.twig'] as $pattern) {
            foreach (glob(\dirname(__DIR__, 2) . $pattern) ?: [] as $path) {
                $templates[basename($path)] = (string) file_get_contents($path);
            }
        }

        $this->assertNotEmpty($templates, 'No template was read at all, this test guards nothing.');

        return $templates;
    }
}
