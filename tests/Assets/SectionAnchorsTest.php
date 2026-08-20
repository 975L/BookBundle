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

// BookSectionsExtension names the sections and the display templates render them: nothing checks the two agree at runtime, and a summary entry pointing at an id no template writes is a link that scrolls nowhere - silently
class SectionAnchorsTest extends TestCase
{
    public function testEverySectionNamedByTheExtensionIsWrittenAsAnIdBySomeTemplate(): void
    {
        $templates = $this->templates();
        $orphans = [];

        foreach ($this->anchors() as $anchor) {
            $written = array_filter($templates, static fn (string $twig): bool => str_contains($twig, sprintf('id="%s"', $anchor)));

            if ([] === $written) {
                $orphans[] = $anchor;
            }
        }

        $this->assertSame([], $orphans, sprintf(
            'BookSectionsExtension offers %s, which no template writes as an id: the summary entry scrolls nowhere.',
            implode(', ', $orphans)
        ));
    }

    // The room a section leaves above itself so the resting summary bar does not cover its own title is UiBundle's "toc-target" - a section given an id but not the class lands under the bar after a jump. A card of platform buttons hands its id to Book:Links, which is where its own class is
    public function testEveryTemplateWritingOneOfThoseIdsAlsoLeavesRoomAboveIt(): void
    {
        $templates = $this->templates();
        $missing = [];

        $this->assertStringContainsString('toc-target', $templates['Links.html.twig'], 'Book:Links no longer leaves room above the card it prints, so every section it renders lands under the bar.');

        foreach ($this->anchors() as $anchor) {
            foreach ($templates as $file => $twig) {
                if (!str_contains($twig, sprintf('id="%s"', $anchor))) {
                    continue;
                }

                if (!str_contains($twig, 'toc-target') && !str_contains($twig, 'c975LBook:Book:Links')) {
                    $missing[] = sprintf('%s (%s)', $anchor, $file);
                }
            }
        }

        $this->assertSame([], $missing, sprintf(
            "A section is given an anchor without the class leaving room above it, so a jump lands it under the bar:\n- %s",
            implode("\n- ", $missing)
        ));
    }

    // The display pages read the list once and render from it: a component called outside that guard would print a section the summary never offers
    public function testTheDisplayPagesRenderTheirSectionsFromTheListTheySummarize(): void
    {
        foreach (['book' => 'book_sections(book)', 'serie' => 'serie_sections(serie)'] as $page => $call) {
            $twig = (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/' . $page . '/display.html.twig');

            $this->assertStringContainsString('{% set sections = ' . $call . ' %}', $twig, sprintf('The %s page no longer reads its sections from the extension.', $page));
            $this->assertStringContainsString("{% set anchors = sections|column('anchor') %}", $twig);
            $this->assertStringContainsString('entries="{{ sections }}"', $twig, sprintf('The %s page summarizes something other than the list it renders.', $page));
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
