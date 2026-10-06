<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Command;

use c975L\BookBundle\Command\BookShopImportCommand;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Repository\BookRepository;
use c975L\UiBundle\Contract\ProductCatalogWriterInterface;
use c975L\UiBundle\Model\CatalogProductItem;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class BookShopImportCommandTest extends TestCase
{
    // A dry run says what the real one does: two PDFs of one product fill one slot, the second is left in the shop
    public function testADryRunFillsEachSlotOnce(): void
    {
        $writer = $this->createMock(ProductCatalogWriterInterface::class);
        $writer->method('itemsWithFile')->willReturn([
            self::item(1, 'le-loup-a4', '/shop/loup-a4.pdf'),
            self::item(2, 'le-loup-a5', '/shop/loup-a5.pdf'),
            self::item(3, 'le-loup-livret', '/shop/loup-livret.pdf'),
        ]);
        $writer->expects($this->never())->method('setKeys');

        $tester = $this->tester($writer);

        $this->assertSame(Command::SUCCESS, $tester->execute(['--dry-run' => true]));
        $display = $tester->getDisplay();
        $this->assertStringContainsString('2 file(s) would be copied', $display);
        $this->assertStringContainsString('already a pdf on "le-loup"', $display);
    }

    // A product matched to no book, or a file of no known kind, stays in the shop
    public function testWhatCannotBeMatchedIsLeftAlone(): void
    {
        $writer = $this->createStub(ProductCatalogWriterInterface::class);
        $writer->method('itemsWithFile')->willReturn([
            new CatalogProductItem(null, 'Mug', '/shop/mug.png', id: 4, slug: 'mug', productSlug: 'mug', productTitle: 'Mug'),
            self::item(5, 'le-loup-zip', '/shop/loup.zip'),
        ]);

        $tester = $this->tester($writer);
        $tester->execute(['--dry-run' => true]);
        $display = $tester->getDisplay();

        $this->assertStringContainsString('no book', $display);
        $this->assertStringContainsString('unknown kind of file', $display);
    }

    public function testWithoutAShopNothingIsImported(): void
    {
        $tester = new CommandTester(new BookShopImportCommand($this->createStub(BookRepository::class), $this->createStub(EntityManagerInterface::class)));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
    }

    private function tester(ProductCatalogWriterInterface $writer): CommandTester
    {
        $repository = $this->createStub(BookRepository::class);
        $repository->method('findBy')->willReturn([new Book()->setSlug('le-loup')->setTitle('Le Loup')]);

        return new CommandTester(new BookShopImportCommand($repository, $this->createStub(EntityManagerInterface::class), $writer));
    }

    private static function item(int $id, string $slug, string $path): CatalogProductItem
    {
        return new CatalogProductItem(null, $slug, $path, 299, id: $id, slug: $slug, productSlug: 'le-loup', productTitle: 'Le Loup');
    }
}
