<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Form\Block;

use c975L\BookBundle\Form\Block\BooksBlockType;
use c975L\BookBundle\Form\Block\BooksToBePublishedBlockType;
use c975L\BookBundle\Form\Block\CategoriesBlockType;
use c975L\BookBundle\Form\Block\ContributorsBlockType;
use c975L\BookBundle\Form\Block\SeriesBlockType;
use c975L\BookBundle\Form\Block\SerieStripsBlockType;
use c975L\UiBundle\Form\TrixEditorType;
use c975L\UiBundle\Service\BlockAnchorSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraints\NotBlank;

// The head every listing kind carries of its own, which is what saves laying a "text_section" above the grid - the two used to be one section on the page and two rows in the back office (see AbstractBookListingBlockType)
class BookListingBlockTypesTest extends TestCase
{
    /** @return array<string, array{type: ?string, options: array<string, mixed>}> */
    private function build(string $class): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });

        new $class(new BlockAnchorSlugger(new AsciiSlugger()))->buildForm($builder, []);

        return $added;
    }

    /** @return list<array{0: string}> */
    public static function listingKinds(): array
    {
        return [
            [SeriesBlockType::class],
            [BooksBlockType::class],
            [BooksToBePublishedBlockType::class],
            [CategoriesBlockType::class],
            [ContributorsBlockType::class],
            [SerieStripsBlockType::class],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('listingKinds')]
    public function testEveryListingCarriesTheSameHead(string $class): void
    {
        $added = $this->build($class);

        $this->assertSame(TextType::class, $added['anchor']['type']);
        $this->assertSame(TextType::class, $added['eyebrow']['type']);
        $this->assertSame(TextType::class, $added['title']['type']);
        $this->assertSame(TrixEditorType::class, $added['content']['type']);
        $this->assertArrayHasKey('background', $added);
    }

    // A page composed before these fields existed renders as a bare grid of covers, so none of the head may be required
    #[\PHPUnit\Framework\Attributes\DataProvider('listingKinds')]
    public function testTheHeadIsOptionalOnEveryKind(string $class): void
    {
        $added = $this->build($class);

        $this->assertFalse($added['eyebrow']['options']['required']);
        $this->assertFalse($added['title']['options']['required']);
        $this->assertFalse($added['content']['options']['required']);
    }

    // How many rows the grid keeps, and which ones - the draw being what makes the block decline its own cache entry (see BookBlockCacheTagProvider)
    #[\PHPUnit\Framework\Attributes\DataProvider('listingKinds')]
    public function testEveryListingSaysHowManyRowsItKeepsAndWhetherItDraws(string $class): void
    {
        $added = $this->build($class);

        $this->assertSame(IntegerType::class, $added['max']['type']);
        $this->assertSame(1, $added['max']['options']['attr']['min']);
        $this->assertSame(CheckboxType::class, $added['random']['type']);
        $this->assertFalse($added['random']['options']['required']);
    }

    // The tile closing the grid belongs to the listings a page of their own answers to: the forthcoming titles and a serie's planches have none
    public function testOnlyTheListingsWithAPageOfTheirOwnOfferTheLastTile(): void
    {
        foreach ([SeriesBlockType::class, BooksBlockType::class, CategoriesBlockType::class, ContributorsBlockType::class] as $class) {
            $this->assertArrayHasKey('displayMore', $this->build($class), $class);
        }

        foreach ([BooksToBePublishedBlockType::class, SerieStripsBlockType::class] as $class) {
            $this->assertArrayNotHasKey('displayMore', $this->build($class), $class);
        }
    }

    // A slug and not a picker, block data being stored as JSON and holding no entity
    public function testTheCatalogIsCutBySlugRatherThanByAPickedRow(): void
    {
        $books = $this->build(BooksBlockType::class);
        $this->assertSame(TextType::class, $books['categorySlug']['type']);
        $this->assertFalse($books['categorySlug']['options']['required']);

        // The one required field of the six: a planches listing naming no serie has nothing to draw
        $strips = $this->build(SerieStripsBlockType::class);
        $this->assertTrue($strips['serieSlug']['options']['required']);
        $this->assertInstanceOf(NotBlank::class, $strips['serieSlug']['options']['constraints'][0]);
    }

    // BlockType translates the embedded data form in the "ui" domain, where every label above would then render raw
    #[\PHPUnit\Framework\Attributes\DataProvider('listingKinds')]
    public function testTheLabelsAreReadInTheBookDomain(string $class): void
    {
        $resolver = new OptionsResolver();
        new $class(new BlockAnchorSlugger(new AsciiSlugger()))->configureOptions($resolver);

        $this->assertSame('book', $resolver->resolve()['translation_domain']);
    }
}
