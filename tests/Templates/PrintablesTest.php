<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Templates;

use c975L\BookBundle\Entity\BookPrintable;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

// A document to print offers its file on a book's page and its book on a listing, with the thumbnail UiBundle cut from it - rendered for real, minus the props tag only the components bundle reads
class PrintablesTest extends TestCase
{
    private const string PRINTABLE = __DIR__ . '/../../templates/components/Book/Printable.html.twig';

    /** @param array<string, mixed> $context */
    private function render(array $context, bool $thumbnail = true): string
    {
        $source = (string) preg_replace('/\{% props .*? %\}/', '', (string) file_get_contents(self::PRINTABLE));
        $twig = new Environment(new ArrayLoader(['printable' => $source]));
        $twig->addFunction(new TwigFunction('asset', static fn (string $path): string => '/' . $path));
        $twig->addFunction(new TwigFunction('asset_exists', static fn (): bool => $thumbnail));

        return $twig->render('printable', [...['url' => '', 'label' => ''], ...$context]);
    }

    // A component a site may call by itself holds the same line as the section list: nothing to print before the book is out
    public function testTheSectionComponentWaitsForTheBookToBeOut(): void
    {
        $this->assertStringContainsString('{% if not book.isToBePublished and book.printables is not empty %}', (string) file_get_contents(__DIR__ . '/../../templates/components/Book/Printables.html.twig'));
    }

    private function printable(): BookPrintable
    {
        return new BookPrintable()->setName('medias/book/printables/conte/page-1.pdf')->setTitle('La page');
    }

    public function testOnABooksPageTheThumbnailDownloadsThePdf(): void
    {
        $html = $this->render(['printable' => $this->printable()]);

        $this->assertStringContainsString('href="/medias/book/printables/conte/page-1.pdf" download', $html);
        $this->assertStringContainsString('src="/medias/book/printables/conte/page-1.webp"', $html);
        $this->assertStringContainsString('<figcaption>La page</figcaption>', $html);
    }

    // On a host without Ghostscript the document has no thumbnail, and a broken image says nothing
    public function testADocumentWithoutThumbnailShowsThePlaceholder(): void
    {
        $this->assertStringContainsString('src="/bundles/c975lbook/images/no-cover.webp"', $this->render(['printable' => $this->printable()], false));
    }

    public function testOnAListingItLeadsToItsBookUnderItsBooksTitle(): void
    {
        $html = $this->render(['printable' => $this->printable(), 'url' => '/book/conte#printables', 'label' => 'Le conte']);

        $this->assertStringContainsString('href="/book/conte#printables"', $html);
        $this->assertStringNotContainsString('download', $html);
        $this->assertStringContainsString('<figcaption>Le conte</figcaption>', $html);
    }
}
