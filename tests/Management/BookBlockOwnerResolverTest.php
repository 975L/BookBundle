<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Management;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookCategory;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Management\BookBlockOwnerResolver;
use c975L\BookBundle\Repository\BookCategoryRepository;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Repository\ContributorRepository;
use c975L\BookBundle\Repository\SerieRepository;
use c975L\BookBundle\Repository\StripRepository;
use PHPUnit\Framework\TestCase;

// The five families of this bundle that compose a page out of blocks, named once here so a controller and a drag-and-drop agree on the word
class BookBlockOwnerResolverTest extends TestCase
{
    private Book $book;

    private BookCategory $category;

    private Contributor $contributor;

    private Serie $serie;

    private Strip $strip;

    private function resolver(): BookBlockOwnerResolver
    {
        $this->book = new Book();
        $this->category = new BookCategory();
        $this->contributor = new Contributor();
        $this->serie = new Serie();
        $this->strip = new Strip();

        $categoryRepository = $this->createStub(BookCategoryRepository::class);
        $categoryRepository->method('find')->willReturn($this->category);
        $bookRepository = $this->createStub(BookRepository::class);
        $bookRepository->method('find')->willReturn($this->book);
        $contributorRepository = $this->createStub(ContributorRepository::class);
        $contributorRepository->method('find')->willReturn($this->contributor);
        $serieRepository = $this->createStub(SerieRepository::class);
        $serieRepository->method('find')->willReturn($this->serie);
        $stripRepository = $this->createStub(StripRepository::class);
        $stripRepository->method('find')->willReturn($this->strip);

        return new BookBlockOwnerResolver($categoryRepository, $bookRepository, $contributorRepository, $serieRepository, $stripRepository);
    }

    public function testTheFiveFamiliesOfThisBundleAreSupported(): void
    {
        $resolver = $this->resolver();

        $types = [
            BookBlockOwnerResolver::TYPE_BOOK,
            BookBlockOwnerResolver::TYPE_SERIE,
            BookBlockOwnerResolver::TYPE_STRIP,
            BookBlockOwnerResolver::TYPE_CONTRIBUTOR,
            BookBlockOwnerResolver::TYPE_CATEGORY,
        ];

        foreach ($types as $type) {
            $this->assertTrue($resolver->supports($type), $type);
        }
    }

    // Another bundle's rows are another resolver's business
    public function testAFamilyOfAnotherBundleIsLeftAlone(): void
    {
        $resolver = $this->resolver();

        $this->assertFalse($resolver->supports('product'));
        $this->assertFalse($resolver->supports('page'));
        $this->assertNull($resolver->find('product', 1));
    }

    public function testEachTypeIsReadFromItsOwnRepository(): void
    {
        $resolver = $this->resolver();

        $this->assertSame($this->book, $resolver->find(BookBlockOwnerResolver::TYPE_BOOK, 1));
        $this->assertSame($this->serie, $resolver->find(BookBlockOwnerResolver::TYPE_SERIE, 1));
        $this->assertSame($this->strip, $resolver->find(BookBlockOwnerResolver::TYPE_STRIP, 1));
        $this->assertSame($this->contributor, $resolver->find(BookBlockOwnerResolver::TYPE_CONTRIBUTOR, 1));
        $this->assertSame($this->category, $resolver->find(BookBlockOwnerResolver::TYPE_CATEGORY, 1));
    }

    // The category carries the bundle's own prefix: "category" alone is ShopBundle's, and a site running both would have the two fighting over it
    public function testTheCategoryIsNamedApartFromAnotherBundlesOwn(): void
    {
        $this->assertSame('book_category', BookBlockOwnerResolver::TYPE_CATEGORY);
    }
}
