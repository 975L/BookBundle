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

// A planche's card is UiBundle's own Card with content nested in it, and nested content is rendered in the card's context: any variable of ours whose name is also one of the card's props is silently replaced by that prop. It cost the listing its rating icons once ("rating" is the card's read-only score) and its mark before that ("icon"), both times without an error and with nothing to see but a card missing a piece
class StripCardShadowingTest extends TestCase
{
    private const string CARD = __DIR__ . '/../../vendor/c975l/core-bundle/UiBundle/templates/components/Card/Card.html.twig';

    private const string STRIP_CARD = __DIR__ . '/../../templates/components/Strip/Card.html.twig';

    public function testNothingNestedInTheCardIsNamedAfterOneOfItsProps(): void
    {
        $props = $this->cardProps();
        $this->assertContains('rating', $props, 'The card no longer declares the prop this test was written for - check it still says what it means.');

        $expressions = $this->nestedExpressions();

        foreach ($props as $prop) {
            // Never after a dot: "card.id" reads a property of ours and has nothing to do with the card's own "id"
            $this->assertDoesNotMatchRegularExpression(
                '/(?<![\w.])' . preg_quote($prop, '/') . '\b/',
                $expressions,
                sprintf('"%s" is one of Card:Card\'s props: nested in it, our own variable of that name is shadowed and reads as the card\'s.', $prop)
            );
        }
    }

    /**
     * The names UiBundle's card declares, read from the card itself rather than listed here: a prop added there tomorrow is one more name this listing may not use.
     *
     * @return string[]
     */
    private function cardProps(): array
    {
        $card = file_get_contents(self::CARD);
        $this->assertIsString($card);
        $this->assertSame(1, preg_match('/\{%\s*props\s*(.+?)%\}/s', $card, $matches));

        $props = [];
        foreach (explode(',', $matches[1]) as $declaration) {
            $name = trim(explode('=', $declaration)[0]);
            if ('' !== $name) {
                $props[] = $name;
            }
        }

        return $props;
    }

    /**
     * Everything the nested content evaluates in the card's context: its tags, and the dynamic attributes of the components it calls in turn.
     *
     * Comments and plain text are left out - a prop's name written in a comment shadows nothing.
     */
    private function nestedExpressions(): string
    {
        $template = file_get_contents(self::STRIP_CARD);
        $this->assertIsString($template);
        $this->assertSame(1, preg_match('/<twig:c975LUi:Card:Card\b[^>]*>(.*)<\/twig:c975LUi:Card:Card>/s', $template, $matches));

        $nested = preg_replace('/\{#.*?#\}/s', '', $matches[1]);
        $this->assertIsString($nested);

        // "{{ ... }}", "{% ... %}" and the dynamic attributes, each reduced to the expression itself
        preg_match_all('/\{\{(.*?)\}\}|\{%(.*?)%\}|:[a-zA-Z]+="([^"]*)"/s', $nested, $expressions, \PREG_SET_ORDER);

        $text = '';
        foreach ($expressions as $expression) {
            $text .= implode('', \array_slice($expression, 1)) . "\n";
        }

        // Quoted literals dropped: a setting called "book-strip-rating-icon" is a name the site gave a setting, not a variable of ours the card could shadow
        $stripped = preg_replace('/"[^"]*"|\'[^\']*\'/', '', $text);
        $this->assertIsString($stripped);

        return $stripped;
    }
}
