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
use c975L\UiBundle\Management\BlockDataImporter;
use c975L\UiBundle\Registry\FormBlockDependencyRegistry;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class BookCategoryImportProviderTest extends TestCase
{
    public function testSupportsOnlyItsOwnKind(): void
    {
        $provider = $this->createProvider();

        $this->assertTrue($provider->supportsImport(BookCategoryImportProvider::KIND));
        $this->assertFalse($provider->supportsImport('book_serie'));
    }

    // What an archive carries of a category, written back exactly as it left: the books filed under it travel under their own kind and are none of this provider's business
    public function testRoundTripRebuildsTheCategory(): void
    {
        $category = new BookCategory()
            ->setSlug('romans')
            ->setTitle('Romans')
            ->setSummary('Des récits au long cours')
            ->setCode('3435')
            ->setPosition(2)
            ->setCreation(new \DateTime('2026-01-02 10:00:00'))
            ->setModification(new \DateTime('2026-01-03 11:00:00'));

        $export = new BookCategoryExportProvider($this->createStub(BookCategoryRepository::class), new BlockDataExporter(sys_get_temp_dir()))
            ->serialize([$category]);

        $persisted = [];
        $result = $this->createProvider(persisted: $persisted)->import($export['items']);

        $this->assertSame(['created' => 1, 'updated' => 0], $result);

        $imported = array_values(array_filter($persisted, static fn (object $e) => $e instanceof BookCategory))[0];
        $this->assertSame('romans', $imported->getSlug());
        $this->assertSame('Romans', $imported->getTitle());
        $this->assertSame('3435', $imported->getCode());
        $this->assertSame(2, $imported->getPosition());
    }

    // The archive is a faithful copy: a category exported out of the trash comes back to the trash rather than onto the site
    public function testImportKeepsACategoryInTheTrash(): void
    {
        $persisted = [];
        $this->createProvider(persisted: $persisted)->import([['slug' => 'romans', 'title' => 'Romans', 'isDeleted' => true]]);

        $this->assertTrue(array_values(array_filter($persisted, static fn (object $e) => $e instanceof BookCategory))[0]->isDeleted());
    }

    // An archive written before the code, the position or the trash existed reads as a category saying nothing of them, rather than as a failed import
    public function testAnArchivePredatingTheColumnsStillImports(): void
    {
        $persisted = [];
        $this->createProvider(persisted: $persisted)->import([['slug' => 'romans', 'title' => 'Romans']]);

        $imported = array_values(array_filter($persisted, static fn (object $e) => $e instanceof BookCategory))[0];
        $this->assertNull($imported->getCode());
        $this->assertSame(0, $imported->getPosition());
        $this->assertFalse($imported->isDeleted());
        $this->assertFalse($imported->isHidden());
        $this->assertNotNull($imported->getCreation());
    }

    // Matched by slug and never by the exported id: a second import writes over the row the site already answers that slug with
    public function testImportWritesOverTheCategoryTheSiteAlreadyHoldsAtThatSlug(): void
    {
        $existing = new BookCategory()->setSlug('romans')->setTitle('Anciens romans')->setCreation(new \DateTime())->setModification(new \DateTime());

        $result = $this->createProvider($existing)->import([['slug' => 'romans', 'title' => 'Romans']]);

        $this->assertSame(['created' => 0, 'updated' => 1], $result);
        $this->assertSame('Romans', $existing->getTitle());
    }

    /** @param list<object> $persisted filled with everything the import hands to the entity manager, the flush being a stub */
    private function createProvider(?BookCategory $existingCategory = null, array &$persisted = []): BookCategoryImportProvider
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $categoryRepository = $this->createStub(BookCategoryRepository::class);
        $categoryRepository->method('findOneBy')->willReturn($existingCategory);

        return new BookCategoryImportProvider(
            $em,
            $categoryRepository,
            new BlockDataImporter($em, $this->createStub(FormBlockDependencyRegistry::class)),
        );
    }
}
