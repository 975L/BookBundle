<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Assets;

use c975L\BookBundle\Twig\BookEditUrlExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// The pencil is three ends nothing checks at runtime: a page gating and mounting UiBundle's overlay, a section handing over the url it was given, and the map naming the field it leads to. Any of them dropped takes the pencils away without a single error
class EditPencilMarkupTest extends TestCase
{
    /** @return iterable<string, array{string, string, string}> */
    public static function pages(): iterable
    {
        yield 'book' => ['book', 'book_edit_urls(book)', 'BOOK_FIELDS'];
        yield 'serie' => ['serie', 'serie_edit_urls(serie)', 'SERIE_FIELDS'];
        yield 'strip' => ['strip', 'strip_edit_urls(strip)', 'STRIP_FIELDS'];
    }

    // Resolving the urls is a query nobody else would see the result of, and the gate is the very role the edit screens ask for - the same the blocks of those pages go by
    #[DataProvider('pages')]
    public function testTheUrlsAreOnlyBuiltForSomeoneTheEditScreenLetsIn(string $page, string $call, string $constant): void
    {
        $this->assertStringContainsString(
            "{% set editUrls = is_granted(config('site-role-editor')) ? " . $call . ' : {} %}',
            $this->template($page),
            sprintf('The %s page no longer gates its pencils on the role its own edit screen asks for.', $page)
        );
    }

    // UiBundle registers the overlay only on a document holding an element asking for it, so a page dropping the mount keeps its attributes and loses every pencil
    #[DataProvider('pages')]
    public function testThePageMountsTheOverlayItsSectionsRelyOn(string $page, string $call, string $constant): void
    {
        $twig = $this->template($page);

        // Mounted alone or with others: a book's page builds its list of controllers (the confetti join the overlay behind a setting), so the name is what is looked for and not the attribute written in one piece
        $this->assertMatchesRegularExpression("/(data-controller=\"[^\"]*blockEditOverlay|'blockEditOverlay')/", $twig, sprintf('The %s page no longer mounts the overlay drawing the pencil.', $page));
        $this->assertStringContainsString("data-edit-label=\"{{ 'label.edit'|trans({}, 'ui') }}\"", $twig);
    }

    // A section reading a key the map does not hold prints an empty url, which is a section left without a pencil and nothing said about it
    #[DataProvider('pages')]
    public function testEveryKeyThePageReadsIsOneTheMapHolds(string $page, string $call, string $constant): void
    {
        preg_match_all('/editUrls\.(\w+)/', $this->template($page), $matches);
        $this->assertNotEmpty($matches[1], sprintf('The %s page reads no url at all, this test guards nothing.', $page));

        $fields = new \ReflectionClassConstant(BookEditUrlExtension::class, $constant)->getValue();

        $this->assertSame([], array_values(array_diff(array_unique($matches[1]), array_keys($fields))), sprintf(
            'The %s page reads a key BookEditUrlExtension::%s does not hold.',
            $page,
            $constant
        ));
    }

    private function template(string $page): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/' . $page . '/display.html.twig');
    }
}
