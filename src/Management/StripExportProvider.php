<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Management;

use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Repository\StripRepository;
use c975L\ConfigBundle\Management\ExportProviderInterface;
use c975L\UiBundle\Management\BlockDataExporter;

// Serializes the strips (their plates, their blocks, real files bundled in the archive) into the shape ContentExporter/StripImportProvider expect - the serie a strip belongs to travels under its own kind, named here by slug so the two can be imported in any order
class StripExportProvider implements ExportProviderInterface
{
    public function __construct(
        private readonly StripRepository $stripRepository,
        private readonly BlockDataExporter $blockDataExporter,
        private readonly MediaArchiver $mediaArchiver,
    ) {
    }

    public function getKind(): string
    {
        return StripImportProvider::KIND;
    }

    public function exportAll(): array
    {
        // findBy([]) rather than a published-only query: the archive is a faithful copy, a strip exported out of the trash coming back to the trash rather than onto the site
        return $this->serialize($this->stripRepository->findBy([]));
    }

    // The rows an admin checked on the catalog's index, serialized exactly as the whole catalog is - what the "export selection" action of the crud screen hands to ContentExporter (see Controller\Management\Trait\TrashableCrudTrait::exportSelection())
    /** @param list<int> $ids */
    public function serializeIds(array $ids): array
    {
        return $this->serialize($this->stripRepository->findBy(['id' => $ids]));
    }

    /** @param iterable<Strip> $strips */
    public function serialize(iterable $strips): array
    {
        $files = [];
        $items = [];
        foreach ($strips as $strip) {
            $items[] = $this->exportStripData($strip, $files);
        }

        return ['items' => $items, 'files' => $files];
    }

    private function exportStripData(Strip $strip, array &$files): array
    {
        $medias = [];
        foreach ($strip->getMedias() as $media) {
            $mediaData = $this->mediaArchiver->export($media, $files);
            if (null !== $mediaData) {
                $medias[] = $mediaData;
            }
        }

        return [
            'slug' => $strip->getSlug(),
            'title' => $strip->getTitle(),
            'number' => $strip->getNumber(),
            // Nothing about charactersSlug, and nothing to say: it is derived from this very line on the way back in (see Strip::setCharacters)
            'characters' => $strip->getCharacters(),
            'summary' => $strip->getSummary(),
            'sourceUrl' => $strip->getSourceUrl(),
            'published' => $strip->getPublished()?->format(\DateTimeInterface::ATOM),
            'creation' => $strip->getCreation()?->format(\DateTimeInterface::ATOM),
            'modification' => $strip->getModification()?->format(\DateTimeInterface::ATOM),
            'isDeleted' => $strip->isDeleted(),
            'hidden' => $strip->isHidden(),
            // The serie named by what it answers at rather than by its id, which never matches between two environments
            'serie' => $strip->getSerie()?->getSlug(),
            'serieTitle' => $strip->getSerie()?->getTitle(),
            'blocks' => $this->blockDataExporter->exportBlocks($strip->getBlocks(), $files),
            'medias' => $medias,
        ];
    }
}
