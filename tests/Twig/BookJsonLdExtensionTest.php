<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Twig;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Service\BookSnippetBuilder;
use c975L\BookBundle\Twig\BookJsonLdExtension;
use PHPUnit\Framework\TestCase;

// The six functions a template publishes its structured data through: each hands its node to the builder and asks it for the script payload, so what a page prints and what the builder makes can never part
class BookJsonLdExtensionTest extends TestCase
{
    private function extension(array $node = ['@type' => 'Book'], string $json = '{"@type":"Book"}'): BookJsonLdExtension
    {
        $builder = $this->createStub(BookSnippetBuilder::class);
        $builder->method('buildBook')->willReturn($node);
        $builder->method('buildSerie')->willReturn($node);
        $builder->method('buildContributor')->willReturn($node);
        $builder->method('buildStrip')->willReturn($node);
        $builder->method('buildBreadcrumb')->willReturn($node);
        $builder->method('buildItemList')->willReturn($node);
        $builder->method('buildJson')->willReturn($json);

        return new BookJsonLdExtension($builder);
    }

    public function testEveryPageOfTheCatalogPublishesItsOwnNode(): void
    {
        $extension = $this->extension();

        $this->assertSame('{"@type":"Book"}', $extension->bookJsonLd(new Book()));
        $this->assertSame('{"@type":"Book"}', $extension->serieJsonLd(new Serie()));
        $this->assertSame('{"@type":"Book"}', $extension->contributorJsonLd(new Contributor()));
        $this->assertSame('{"@type":"Book"}', $extension->stripJsonLd(new Strip()));
        $this->assertSame('{"@type":"Book"}', $extension->breadcrumbJsonLd([['name' => 'Livres', 'url' => '/livres']]));
        $this->assertSame('{"@type":"Book"}', $extension->itemListJsonLd([['name' => 'Un livre', 'url' => '/livre/un']]));
    }

    // A page with nothing to publish prints no script tag at all, rather than an empty one a crawler would read as a broken graph
    public function testAPageWithNothingToPublishPrintsNothing(): void
    {
        $extension = $this->extension(node: [], json: '');

        $this->assertSame('', $extension->bookJsonLd(new Book()));
        $this->assertSame('', $extension->breadcrumbJsonLd([]));
        $this->assertSame('', $extension->itemListJsonLd([]));
    }

    // The image and the address are the page's own, the builder holding no url generator: a book rendered from two sites publishes two addresses
    public function testThePageHandsOverItsOwnImageAndAddress(): void
    {
        $builder = $this->createMock(BookSnippetBuilder::class);
        $builder->expects($this->once())
            ->method('buildBook')
            ->with($this->isInstanceOf(Book::class), 'https://example.org/cover.webp', 'https://example.org/livre/un')
            ->willReturn(['@type' => 'Book'])
        ;
        $builder->method('buildJson')->willReturn('{"@type":"Book"}');

        new BookJsonLdExtension($builder)->bookJsonLd(new Book(), 'https://example.org/cover.webp', 'https://example.org/livre/un');
    }

    // A listing's second page numbers its rows from where the first left off, or a crawler reads twenty items all ranked first
    public function testAListingCarriesTheRankItsPageStartsAt(): void
    {
        $builder = $this->createMock(BookSnippetBuilder::class);
        $builder->expects($this->once())->method('buildItemList')->with([], 20)->willReturn([]);
        $builder->method('buildJson')->willReturn('');

        new BookJsonLdExtension($builder)->itemListJsonLd([], 20);
    }
}
