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

// The sections of a book's page are cached per language of the page and per language their labels are written in: "/livre/x" and "/fr/livre/x" read the same request locale with labels in two languages
class BookSectionsCacheKeyTest extends TestCase
{
    private const string DISPLAY = __DIR__ . '/../../templates/book/display.html.twig';

    public function testTheKeyCarriesTheLanguageOfTheLabels(): void
    {
        $this->assertStringContainsString(
            "{% cache 'book_sections_' ~ book.id ~ '_' ~ app.request.locale ~ '_' ~ book_ui_locale(book.language) ttl(",
            (string) file_get_contents(self::DISPLAY)
        );
    }
}
