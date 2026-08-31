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

// The three listings are called with three different shapes of the same answer: the blocks interpolate their checkbox as "1"/"", the pages spell out "true"/"false", and any non-empty string is true in Twig - so the value is coerced once at the top of each and never compared to a string, a "false" compared as one reading true and a "1" compared to "true" reading false
class DisplayMoreCoercionTest extends TestCase
{
    private const array LISTINGS = [
        'Book/Books.html.twig',
        'Serie/Series.html.twig',
        'Contributor/Contributors.html.twig',
    ];

    public function testEachListingCoercesItsDisplayMoreRatherThanComparingItToAString(): void
    {
        foreach (self::LISTINGS as $listing) {
            $template = $this->read($listing);

            $this->assertStringContainsString(
                '{% set displayMore = displayMore|default(false)|to_bool %}',
                $template,
                sprintf('"%s" no longer brings displayMore down to a boolean, so the checkbox of its block goes unread.', $listing)
            );
            $this->assertStringContainsString('{% if displayMore %}', $template, sprintf('"%s" no longer tests displayMore as a boolean.', $listing));
            $this->assertStringNotContainsString(
                'displayMore ==',
                $template,
                sprintf('"%s" compares displayMore to a string again: "false" is a true string, and the block writes "1", not "true".', $listing)
            );
        }
    }

    private function read(string $listing): string
    {
        $template = file_get_contents(__DIR__ . '/../../templates/components/' . $listing);
        $this->assertIsString($template);

        return $template;
    }
}
