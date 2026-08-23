<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Management;

use c975L\BookBundle\Controller\Management\BookCrudController;
use c975L\BookBundle\Controller\Management\SerieCrudController;
use c975L\BookBundle\Controller\Management\StripCrudController;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Repository\SerieRepository;
use c975L\BookBundle\Repository\StripRepository;
use c975L\UiBundle\Contract\BlockEditUrlProviderInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Service\BlockFocusUrl;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

// Resolves, for UiBundle's front-end "Edit this block" hover button, the EasyAdmin edit URL of the Book, Serie or Strip owning a given Block - so the blocks composed at the foot of a display page hover the same pencil as the sections above them (see BookEditUrlExtension for those)
class BookBlockEditUrlProvider implements BlockEditUrlProviderInterface
{
    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly BookRepository $bookRepository,
        private readonly SerieRepository $serieRepository,
        private readonly StripRepository $stripRepository,
    ) {
    }

    public function getEditUrls(array $blocks): array
    {
        $blockIds = array_filter(array_map(static fn (Block $block): ?int => $block->getId(), $blocks));

        if ([] === $blockIds) {
            return [];
        }

        $urls = [];

        // One query per family rather than one per block, and only the owners actually holding one of those rows come back
        $families = [
            BookCrudController::class => $this->bookRepository->findByBlockIds($blockIds),
            SerieCrudController::class => $this->serieRepository->findByBlockIds($blockIds),
            StripCrudController::class => $this->stripRepository->findByBlockIds($blockIds),
        ];

        foreach ($families as $crudControllerFqcn => $owners) {
            foreach ($owners as $owner) {
                $urls += $this->urlsOf($crudControllerFqcn, $owner, $blockIds);
            }
        }

        return $urls;
    }

    /**
     * @param int[] $blockIds
     *
     * @return array<int, string>
     */
    private function urlsOf(string $crudControllerFqcn, Book | Serie | Strip $owner, array $blockIds): array
    {
        $urls = [];

        foreach ($owner->getBlocks() as $block) {
            if (\in_array($block->getId(), $blockIds, true)) {
                $urls[$block->getId()] = BlockFocusUrl::build($this->adminUrlGenerator, $crudControllerFqcn, $owner->getId(), $block);
            }
        }

        return $urls;
    }
}
