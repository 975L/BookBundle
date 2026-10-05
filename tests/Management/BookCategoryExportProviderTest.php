<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Management;

use c975L\BookBundle\Entity\BookCategory;
use c975L\BookBundle\Management\BookCategoryExportProvider;
use c975L\BookBundle\Management\BookCategoryImportProvider;
use c975L\BookBundle\Repository\BookCategoryRepository;
use c975L\UiBundle\Management\BlockDataExporter;
use PHPUnit\Framework\TestCase;

class BookCategoryExportProviderTest extends TestCase
{
    public function testGetKindMatchesBookCategoryImportProvider(): void
    {
        $this->assertSame(BookCategoryImportProvider::KIND, $this->createProvider()->getKind());
    }

    // findBy([]) and not findAll(), which hides the trash: an archive is a faithful copy
    public function testExportAllAsksForEveryCategoryIncludingTheTrash(): void
    {
        $repository = $this->createMock(BookCategoryRepository::class);
        $repository->expects($this->once())->method('findBy')->with([])->willReturn([$this->createCategory()]);

        $this->assertSame(['albums'], array_column($this->createProvider($repository)->exportAll()['items'], 'slug'));
    }

    // One code per classification, carried as the category holds them for BookCategoryImportProvider to read back
    public function testSerializeCarriesTheCodesAndWhereTheCategoryStands(): void
    {
        $category = $this->createCategory()->setHidden(true);
        $category->setIsDeleted(true);

        $item = $this->createProvider()->serialize([$category])['items'][0];

        $this->assertSame('Albums', $item['title']);
        $this->assertSame(['clil' => '3730', 'thema' => 'YBCS1', 'bisac' => 'JUV010000'], $item['codes']);
        $this->assertArrayNotHasKey('code', $item);
        $this->assertTrue($item['hidden']);
        $this->assertTrue($item['isDeleted']);
        $this->assertSame(2, $item['position']);
    }

    private function createCategory(): BookCategory
    {
        return new BookCategory()
            ->setSlug('albums')
            ->setTitle('Albums')
            ->setCodes(['clil' => '3730', 'thema' => 'YBCS1', 'bisac' => 'JUV010000'])
            ->setPosition(2);
    }

    private function createProvider(?BookCategoryRepository $repository = null): BookCategoryExportProvider
    {
        return new BookCategoryExportProvider(
            $repository ?? $this->createStub(BookCategoryRepository::class),
            new BlockDataExporter(sys_get_temp_dir()),
        );
    }
}
