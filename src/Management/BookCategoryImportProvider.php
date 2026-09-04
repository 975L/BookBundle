<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Management;

use c975L\BookBundle\Entity\BookCategory;
use c975L\BookBundle\Repository\BookCategoryRepository;
use c975L\ConfigBundle\Management\ImportProviderInterface;
use c975L\UiBundle\Management\BlockDataImporter;
use Doctrine\ORM\EntityManagerInterface;

// Imports a "book_category" content export (see BookCategoryExportProvider) - matches by slug, which is what a category answers at, and never by the exported id. The books filed under it come under their own kind, so a category imported alone keeps whatever the site already files under it
class BookCategoryImportProvider implements ImportProviderInterface
{
    public const KIND = 'book_category';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BookCategoryRepository $categoryRepository,
        private readonly BlockDataImporter $blockDataImporter,
    ) {
    }

    public function supportsImport(string $kind): bool
    {
        return self::KIND === $kind;
    }

    public function import(array $items, ?string $filesDir = null): array
    {
        $created = 0;
        $updated = 0;

        foreach ($items as $item) {
            $category = $this->categoryRepository->findOneBy(['slug' => $item['slug']]);
            $isNew = null === $category;
            $category ??= new BookCategory();

            $this->fillCategory($category, $item);

            $this->replaceBlocks($category, $item['blocks'] ?? [], $filesDir);

            $this->em->persist($category);
            $isNew ? $created++ : $updated++;
        }

        $this->em->flush();

        return ['created' => $created, 'updated' => $updated];
    }

    // What the category is, and where it stands: a round-trip must not put back on the site what an admin had taken off it
    private function fillCategory(BookCategory $category, array $item): void
    {
        $category
            ->setSlug($item['slug'])
            ->setTitle($item['title'])
            ->setSummary($item['summary'] ?? null)
            ->setCode($item['code'] ?? null)
            ->setPosition($item['position'] ?? 0)
            // Both columns are required, so an archive predating them dates the category from the import rather than leaving it unwritten
            ->setCreation(isset($item['creation']) ? new \DateTime($item['creation']) : new \DateTime())
            ->setModification(isset($item['modification']) ? new \DateTime($item['modification']) : new \DateTime())
            ->setIsDeleted($item['isDeleted'] ?? false)
            ->setHidden($item['hidden'] ?? false);
    }

    // Existing Blocks have no natural key to match the imported ones against, so the whole collection is replaced - BlockRemovalListener removes the orphaned rows (and their Medias) on flush, same as SerieImportProvider
    private function replaceBlocks(BookCategory $category, array $blocksData, ?string $filesDir): void
    {
        foreach ($category->getBlocks()->toArray() as $existingBlock) {
            $category->removeBlock($existingBlock);
        }

        foreach ($this->blockDataImporter->buildBlocks($blocksData, $filesDir) as $block) {
            $category->addBlock($block);
        }
    }
}
