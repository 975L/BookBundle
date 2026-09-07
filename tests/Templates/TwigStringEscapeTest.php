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

// Twig ignores a backslash it does not know and has deprecated it since 3.12, so a regular expression typed in a template - "matches '/\.mp4$/'" - logged a deprecation on every render and loses its escape outright in Twig 4. An extension is read with "ends with" here, but the trap is open to any template writing a pattern
class TwigStringEscapeTest extends TestCase
{
    private const string TEMPLATES = __DIR__ . '/../../templates';

    // The escapes Twig's own lexer decodes whatever the literal: SPECIAL_CHARS, a doubled backslash, an interpolation, a hexadecimal and an octal (see Twig\Lexer::stripcslashes())
    private const string KNOWN_ESCAPES = '\\\\|[fnrtv]|#\{|x[0-9A-Fa-f]|[0-7]';

    public function testNoTemplateEscapesACharacterTwigDoesNotKnow(): void
    {
        foreach ($this->stringLiterals() as $template => $literals) {
            foreach ($literals as $literal) {
                // The sixth escape is the literal's own quote and only that one: Twig deprecates a double quote escaped inside a single-quoted string just as it does a dot
                $this->assertSame(
                    0,
                    preg_match('/\\\\(?!' . self::KNOWN_ESCAPES . '|' . $literal[0] . ')/', $literal),
                    sprintf('%s: "%s" escapes a character Twig ignores - it is deprecated since 3.12 and dropped in Twig 4, so the pattern stops meaning what it says.', $template, $literal),
                );
            }
        }
    }

    // The literals of every template's expressions, the raw text around them left out - a backslash printed as content escapes nothing
    /** @return array<string, list<string>> */
    private function stringLiterals(): array
    {
        $literals = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::TEMPLATES)) as $file) {
            if (!$file->isFile() || 'twig' !== $file->getExtension()) {
                continue;
            }

            $template = file_get_contents($file->getPathname());
            $this->assertIsString($template);

            preg_match_all('/\{\{(.*?)\}\}|\{%(.*?)%\}/s', $template, $expressions, \PREG_SET_ORDER);

            $found = [];
            foreach ($expressions as $expression) {
                preg_match_all('/"[^"]*"|\'[^\']*\'/', implode('', \array_slice($expression, 1)), $quoted);
                $found = [...$found, ...$quoted[0]];
            }

            if ([] !== $found) {
                $literals[$file->getFilename()] = $found;
            }
        }

        $this->assertNotEmpty($literals, 'No template literal was read at all - the scan is broken, not the templates.');

        return $literals;
    }
}
