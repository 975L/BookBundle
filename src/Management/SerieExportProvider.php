<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Management;

use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Repository\SerieRepository;
use c975L\ConfigBundle\Management\ExportProviderInterface;
use c975L\UiBundle\Management\BlockDataExporter;

// Serializes the series (their covers, their logos and their editorial blocks, real files bundled in the archive) into the shape ContentExporter/SerieImportProvider expect - the books and the strips they hold travel under their own kinds, each naming its serie by slug
class SerieExportProvider implements ExportProviderInterface
{
    public function __construct(
        private readonly SerieRepository $serieRepository,
        private readonly BlockDataExporter $blockDataExporter,
        private readonly MediaArchiver $mediaArchiver,
    ) {
    }

    public function getKind(): string
    {
        return SerieImportProvider::KIND;
    }

    public function exportAll(): array
    {
        // findBy([]) rather than findAll(), which hides the trash (see SerieRepository): the archive is a faithful copy, a serie exported out of the trash coming back to the trash rather than onto the site
        return $this->serialize($this->serieRepository->findBy([]));
    }

    // The rows an admin checked on the catalog's index, serialized exactly as the whole catalog is - what the "export selection" action of the crud screen hands to ContentExporter (see Controller\Management\Trait\TrashableCrudTrait::exportSelection())
    // @param list<int> $ids
    public function serializeIds(array $ids): array
    {
        return $this->serialize($this->serieRepository->findBy(['id' => $ids]));
    }

    // @param iterable<Serie> $series
    public function serialize(iterable $series): array
    {
        $files = [];
        $items = [];
        foreach ($series as $serie) {
            $items[] = $this->exportSerieData($serie, $files);
        }

        return ['items' => $items, 'files' => $files];
    }

    private function exportSerieData(Serie $serie, array &$files): array
    {
        $medias = [];
        foreach ($serie->getMedias() as $media) {
            $mediaData = $this->mediaArchiver->export($media, $files);
            if (null !== $mediaData) {
                $medias[] = $mediaData;
            }
        }

        return [
            'slug' => $serie->getSlug(),
            'title' => $serie->getTitle(),
            'summary' => $serie->getSummary(),
            'kind' => $serie->getKind(),
            'language' => $serie->getLanguage(),
            'author' => $serie->getAuthor(),
            'authorWebsite' => $serie->getAuthorWebsite(),
            'illustrator' => $serie->getIllustrator(),
            'illustratorWebsite' => $serie->getIllustratorWebsite(),
            'creation' => $serie->getCreation()?->format(\DateTimeInterface::ATOM),
            'modification' => $serie->getModification()?->format(\DateTimeInterface::ATOM),
            'isDeleted' => $serie->isDeleted(),
            'hidden' => $serie->isHidden(),
            // The serie's editorial page, carried the same way PageExportProvider carries a Page's, its own medias joining the archive
            'blocks' => $this->blockDataExporter->exportBlocks($serie->getBlocks(), $files),
            'medias' => $medias,
        ];
    }
}
