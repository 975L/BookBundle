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

// A {% set %} drops the html-safe marking of a json_ld() payload, serving the graph escaped and unreadable to a parser: this reads that the templates still say "raw"
class StructuredDataMarkupTest extends TestCase
{
    // Every printed variable inside a <script type="application/ld+json"> tag of the bundle's templates
    private const string PATTERN = '#<script[^>]*type="application/ld\+json"[^>]*>\{\{\s*([^}]+?)\s*\}\}#';

    public function testEveryStructuredDataBlockIsPrintedRaw(): void
    {
        $printed = 0;

        foreach ($this->templates() as $path) {
            preg_match_all(self::PATTERN, (string) file_get_contents($path), $matches);

            foreach ($matches[1] as $expression) {
                ++$printed;

                // A direct call keeps the function's own is_safe marking, a variable needs "raw" back - both are correct, anything else is escaped
                $this->assertTrue(
                    str_contains($expression, '|raw') || str_contains($expression, '('),
                    sprintf('"%s" prints "%s" escaped: the graph is served as &quot; and no parser reads it.', $path, $expression)
                );
            }
        }

        // A pattern matching nothing would pass every assertion above without reading a single tag
        $this->assertGreaterThan(0, $printed, 'No structured data block found at all: the pattern no longer matches the templates.');
    }

    /**
     * Every Twig template of the bundle.
     *
     * @return iterable<string>
     */
    private function templates(): iterable
    {
        $directory = new \RecursiveDirectoryIterator(\dirname(__DIR__, 2) . '/templates');

        foreach (new \RecursiveIteratorIterator($directory) as $file) {
            if ($file->isFile() && str_ends_with((string) $file->getFilename(), '.html.twig')) {
                yield (string) $file->getPathname();
            }
        }
    }
}
