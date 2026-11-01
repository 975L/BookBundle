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

// A book not out yet or of a serie set aside has its page, but BookFavoriteItemProvider names neither: the form its reviews fold onto answers 404
class BookReviewsSectionTest extends TestCase
{
    private const string DISPLAY = __DIR__ . '/../../templates/book/display.html.twig';

    public function testAReviewIsOnlyAskedForABookTheFormCanName(): void
    {
        $template = (string) file_get_contents(self::DISPLAY);

        $this->assertMatchesRegularExpression(
            "/\{% if not book\.toBePublished and not book\.serie\.hidden\|default\(false\) %\}\s*\{\{ ui_reviews_section\('book', book\.id\) \}\}\s*\{% endif %\}/",
            $template
        );
    }
}
