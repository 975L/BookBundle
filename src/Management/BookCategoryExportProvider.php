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
use c975L\ConfigBundle\Management\ExportProviderInterface;
use c975L\UiBundle\Management\BlockDataExporter;

// Serializes the categories and their editorial blocks into the shape ContentExporter/BookCategoryImportProvider expect - the books filed under them travel under their own kind, each naming its categories by slug (see BookExportProvider)
class BookCategoryExportProvider implements ExportProviderInterface
{
    public function __construct(
        private readonly BookCategoryRepository $categoryRepository,
        private readonly BlockDataExporter $blockDataExporter,
    ) {
    }

    public function getKind(): string
    {
        return BookCategoryImportProvider::KIND;
    }

    public function exportAll(): array
    {
        // findBy([]) rather than findAll(), which hides the trash (see BookCategoryRepository): the archive is a faithful copy, a category exported out of the trash coming back to the trash rather than onto the site
        return $this->serialize($this->categoryRepository->findBy([]));
    }

    // The rows an admin checked on the index, serialized exactly as the whole list is - what the "export selection" action of the crud screen hands to ContentExporter (see Controller\Management\Trait\TrashableCrudTrait::exportSelection())
    /** @param list<int> $ids */
    public function serializeIds(array $ids): array
    {
        return $this->serialize($this->categoryRepository->findBy(['id' => $ids]));
    }

    /** @param iterable<BookCategory> $categories */
    public function serialize(iterable $categories): array
    {
        $files = [];
        $items = [];
        foreach ($categories as $category) {
            $items[] = $this->exportCategoryData($category, $files);
        }

        return ['items' => $items, 'files' => $files];
    }

    private function exportCategoryData(BookCategory $category, array &$files): array
    {
        return [
            'slug' => $category->getSlug(),
            'title' => $category->getTitle(),
            'summary' => $category->getSummary(),
            'code' => $category->getCode(),
            'position' => $category->getPosition(),
            'creation' => $category->getCreation()?->format(\DateTimeInterface::ATOM),
            'modification' => $category->getModification()?->format(\DateTimeInterface::ATOM),
            'isDeleted' => $category->isDeleted(),
            'hidden' => $category->isHidden(),
            // The category's editorial page, carried the same way PageExportProvider carries a Page's, its own medias joining the archive
            'blocks' => $this->blockDataExporter->exportBlocks($category->getBlocks(), $files),
        ];
    }
}
