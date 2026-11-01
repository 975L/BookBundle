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
use Symfony\Component\Finder\Finder;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

// What an editor writes reaches a visitor's page through the sanitizer, never as it was typed
class RichTextEscapingTest extends TestCase
{
    // What may stay raw: the JSON-LD (JsonLdBuilder hex-encodes its tags), the section's own rendered fragment, and an attribute the template writes itself
    private const array RAW_ALLOWED = ['jsonLd|raw', "body|default('')|raw", 'loading|raw'];

    // Each field an editor writes in Trix, in the template printing it
    private const array RICH_FIELDS = [
        'components/Book/Card.html.twig' => 'book.summary',
        'category/display.html.twig' => 'category.summary',
        'components/Category/Category.html.twig' => 'category.summary',
        'components/Contributor/Hero.html.twig' => 'contributor.summary',
        'components/Strip/Card.html.twig' => 'card.summary',
        'components/Strip/Summary.html.twig' => 'summary',
        'components/Serie/Characters.html.twig' => 'character.presentation',
        'components/Listing/Section.html.twig' => 'text',
    ];

    public function testNoTemplatePrintsAnythingElseRaw(): void
    {
        $raw = [];
        foreach (new Finder()->files()->in(\dirname(__DIR__, 2) . '/templates')->name('*.twig') as $file) {
            $source = (string) preg_replace('/\{#.*?#\}/s', '', $file->getContents());
            preg_match_all('/[\w.\']+(?:\([^)]*\))?(?:\|\w+(?:\([^)]*\))?)*\|raw\b/', $source, $matches);
            // Written in a form the pattern cannot name, "(a ~ b)|raw" for one: refused as well rather than passing unread
            $this->assertSame(preg_match_all('/\|\s*raw\b/', $source), \count($matches[0]), $file->getRelativePathname());
            foreach (array_diff($matches[0], self::RAW_ALLOWED) as $expression) {
                $raw[] = $file->getRelativePathname() . ': ' . $expression;
            }
        }

        $this->assertSame([], $raw);
    }

    // Under the sanitizer CoreBundle declares for them
    public function testTheRichFieldsGoThroughTheSanitizer(): void
    {
        foreach (self::RICH_FIELDS as $template => $field) {
            $this->assertStringContainsString($field . "|sanitize_html('c975l.rich')", $this->read($template), $template);
        }
    }

    // A title holds no markup: what is typed in it is escaped, the line break of its " - " alone being written
    public function testATitleIsEscapedAroundItsLineBreak(): void
    {
        preg_match('#<\{\{ level \}\} class="book-card__title">.*</\{\{ level \}\}>#', $this->read('components/Book/Book.html.twig'), $matches);
        $twig = new Environment(new ArrayLoader(['title' => $matches[0]]));

        $html = $twig->render('title', ['level' => 'h3', 'book' => ['title' => 'Tom & <img src=x onerror=alert(1)> - Tome 2']]);

        $this->assertSame('<h3 class="book-card__title">Tom &amp; &lt;img src=x onerror=alert(1)&gt; <br>Tome 2</h3>', $html);
    }

    // What a title without markup used to give, through replace({' - ': ' <br>'})|raw
    public function testAnOrdinaryTitleReadsAsBefore(): void
    {
        preg_match('#<\{\{ level \}\} class="book-card__title">.*</\{\{ level \}\}>#', $this->read('components/Book/Book.html.twig'), $matches);
        $twig = new Environment(new ArrayLoader(['title' => $matches[0]]));

        foreach (['Les Jumeaux - La Piscine' => 'Les Jumeaux <br>La Piscine', 'Un - Deux - Trois' => 'Un <br>Deux <br>Trois', 'Sans séparateur' => 'Sans séparateur', 'Porte-clés' => 'Porte-clés', 'Fin - ' => 'Fin <br>', '' => ''] as $title => $expected) {
            $this->assertSame('<h3 class="book-card__title">' . $expected . '</h3>', $twig->render('title', ['level' => 'h3', 'book' => ['title' => (string) $title]]));
        }

        $this->assertSame('<h3 class="book-card__title"></h3>', $twig->render('title', ['level' => 'h3', 'book' => ['title' => null]]));
    }

    private function read(string $template): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/' . $template);
    }
}
