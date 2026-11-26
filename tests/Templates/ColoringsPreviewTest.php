<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Templates;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookColoring;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

// The preview of a book's drawings: one heading per level, each drawing with its image, its day ahead and its crayons, and a sentence when there is none (see BookColoringsPreviewController)
class ColoringsPreviewTest extends TestCase
{
    /** @param list<array{kind: ?string, label: ?string, colorings: list<BookColoring>}> $groups */
    private function render(array $groups): string
    {
        $source = (string) file_get_contents(__DIR__ . '/../../templates/management/book_colorings_preview.html.twig');
        $twig = new Environment(new ArrayLoader([
            'preview' => $source,
            '@EasyAdmin/page/content.html.twig' => '{% block page_title %}{% endblock %}{% block main %}{% endblock %}',
        ]));
        $twig->addFunction(new TwigFunction('asset', static fn (string $path): string => '/' . $path));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));

        return $twig->render('preview', ['book' => new Book()->setTitle('item-1'), 'groups' => $groups]);
    }

    public function testEachLevelHoldsItsDrawingsWithTheirDayAndCrayons(): void
    {
        $ahead = new BookColoring()->setName('medias/book/colorings/item-1.png')->setTitle('item-1')->setPalette(['#ff0000'])->setPublishedOn(new \DateTimeImmutable('+1 year'));
        $out = new BookColoring()->setName('medias/book/colorings/item-2.png')->setTitle('item-2');

        $html = $this->render([
            ['kind' => 'small', 'label' => 'item-small', 'colorings' => [$ahead]],
            ['kind' => null, 'label' => null, 'colorings' => [$out]],
        ]);

        $this->assertStringContainsString('item-small (1)', $html);
        $this->assertStringContainsString('label.coloring_no_kind (1)', $html);
        $this->assertStringContainsString('src="/medias/book/colorings/item-1.png"', $html);
        $this->assertStringContainsString('fill="#ff0000"', $html);
        $this->assertStringContainsString('color="warning"', $html);
        $this->assertStringContainsString('label.coloring_out_now', $html);
    }

    public function testABookWithoutDrawingsSaysSo(): void
    {
        $this->assertStringContainsString('label.colorings_preview_none', $this->render([]));
    }
}
