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

// A component's docblock is the call a consuming site copies. Written compact="false", Twig hands the component the string "false", which is true: whoever follows the docblock gets the opposite of what it asks for. The expression form, :compact="false", is the one that passes a boolean
class BooleanPropDocblockTest extends TestCase
{
    private const string COMPONENTS = __DIR__ . '/../../templates/components';

    public function testABooleanPropIsDocumentedInItsExpressionForm(): void
    {
        $checked = 0;

        foreach ($this->componentTemplates() as $path) {
            $template = file_get_contents($path);
            $this->assertIsString($template);

            $docblock = $this->docblock($template);
            if (null === $docblock) {
                continue;
            }

            foreach ($this->booleanProps($template) as $prop) {
                ++$checked;

                // The name written without the leading colon, and never as part of a longer one
                $this->assertDoesNotMatchRegularExpression(
                    '/(?<![\w:])' . preg_quote($prop, '/') . '\s*=/',
                    $docblock,
                    sprintf('"%s" is a boolean prop of %s: its docblock must show it as :%s="…", the string form passing "false" as true.', $prop, basename($path), $prop)
                );
            }
        }

        $this->assertGreaterThan(0, $checked, 'No boolean prop was checked - the components moved, or none declares one any more.');
    }

    /**
     * Every component template of the bundle.
     *
     * @return string[]
     */
    private function componentTemplates(): array
    {
        $paths = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::COMPONENTS, \RecursiveDirectoryIterator::SKIP_DOTS));

        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.html.twig')) {
                $paths[] = $file->getPathname();
            }
        }

        return $paths;
    }

    /**
     * The example call the template opens on, if it holds one - what a site copies to call the component.
     */
    private function docblock(string $template): ?string
    {
        if (1 !== preg_match('/\{#\s*(<twig:.*?)#\}/s', $template, $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * The props whose default is a boolean, read from the declaration itself: a boolean prop added tomorrow is checked without touching this test.
     *
     * @return string[]
     */
    private function booleanProps(string $template): array
    {
        if (1 !== preg_match('/\{%\s*props\s*(.+?)%\}/s', $template, $matches)) {
            return [];
        }

        $props = [];
        foreach (explode(',', $matches[1]) as $declaration) {
            $parts = explode('=', $declaration);
            if (2 === \count($parts) && \in_array(trim($parts[1]), ['true', 'false'], true)) {
                $props[] = trim($parts[0]);
            }
        }

        return $props;
    }
}
