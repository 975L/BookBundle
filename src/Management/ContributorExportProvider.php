<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Management;

use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\ContributorLink;
use c975L\BookBundle\Repository\ContributorRepository;
use c975L\ConfigBundle\Management\ExportProviderInterface;
use c975L\UiBundle\Management\BlockDataExporter;

// Serializes the people (their portrait, their background and their editorial blocks, real files bundled in the archive) into the shape ContentExporter/ContributorImportProvider expect - what they signed travels under its own kind, each book and each serie naming them by name
class ContributorExportProvider implements ExportProviderInterface
{
    public function __construct(
        private readonly ContributorRepository $contributorRepository,
        private readonly BlockDataExporter $blockDataExporter,
        private readonly MediaArchiver $mediaArchiver,
    ) {
    }

    public function getKind(): string
    {
        return ContributorImportProvider::KIND;
    }

    public function exportAll(): array
    {
        // findBy([]) rather than findAll(), which hides the trash (see ContributorRepository): the archive is a faithful copy, someone exported out of the trash coming back to the trash rather than onto the site
        return $this->serialize($this->contributorRepository->findBy([]));
    }

    // The rows an admin checked on the index, serialized exactly as the whole list is - what the "export selection" action of the crud screen hands to ContentExporter (see Controller\Management\Trait\TrashableCrudTrait::exportSelection())
    /** @param list<int> $ids */
    public function serializeIds(array $ids): array
    {
        return $this->serialize($this->contributorRepository->findBy(['id' => $ids]));
    }

    /** @param iterable<Contributor> $contributors */
    public function serialize(iterable $contributors): array
    {
        $files = [];
        $items = [];
        foreach ($contributors as $contributor) {
            $items[] = $this->exportContributorData($contributor, $files);
        }

        return ['items' => $items, 'files' => $files];
    }

    private function exportContributorData(Contributor $contributor, array &$files): array
    {
        $medias = [];
        foreach ($contributor->getMedias() as $media) {
            $mediaData = $this->mediaArchiver->export($media, $files);
            if (null !== $mediaData) {
                $medias[] = $mediaData;
            }
        }

        return [
            'slug' => $contributor->getSlug(),
            'name' => $contributor->getName(),
            'summary' => $contributor->getSummary(),
            'website' => $contributor->getWebsite(),
            'position' => $contributor->getPosition(),
            'creation' => $contributor->getCreation()?->format(\DateTimeInterface::ATOM),
            'modification' => $contributor->getModification()?->format(\DateTimeInterface::ATOM),
            'isDeleted' => $contributor->isDeleted(),
            'hidden' => $contributor->isHidden(),
            // Their editorial page, carried the same way SerieExportProvider carries a serie's, its own medias joining the archive
            'blocks' => $this->blockDataExporter->exportBlocks($contributor->getBlocks(), $files),
            'medias' => $medias,
            // Where their books are bought, each address as it is stored (see BookExportProvider, which carries a book's the same way)
            'links' => array_map(
                static fn (ContributorLink $link): array => [
                    'kind' => $link->getKind(),
                    'url' => $link->getUrl(),
                    'position' => $link->getPosition(),
                ],
                $contributor->getLinks()->toArray()
            ),
        ];
    }
}
