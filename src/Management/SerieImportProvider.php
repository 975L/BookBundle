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
use c975L\BookBundle\Entity\SerieMedia;
use c975L\BookBundle\Repository\SerieRepository;
use c975L\ConfigBundle\Management\ImportProviderInterface;
use c975L\UiBundle\Management\BlockDataImporter;
use Doctrine\ORM\EntityManagerInterface;

// Imports a "book_serie" content export (see SerieExportProvider) - matches by slug, which is what a serie answers at, and never by the exported id. The books and the strips it holds come under their own kinds, so a serie imported alone keeps whatever the site already hangs off it
class SerieImportProvider implements ImportProviderInterface
{
    public const KIND = 'book_serie';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SerieRepository $serieRepository,
        private readonly BlockDataImporter $blockDataImporter,
        private readonly MediaArchiver $mediaArchiver,
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
        $written = [];

        foreach ($items as $item) {
            $serie = $this->serieRepository->findOneBy(['slug' => $item['slug']]);
            $isNew = null === $serie;
            $serie ??= new Serie();

            $this->fillSerie($serie, $item);

            $this->replaceBlocks($serie, $item['blocks'] ?? [], $filesDir);

            $written = [...$written, ...$this->mediaArchiver->sync(
                $serie->getMedias(),
                $item['medias'] ?? [],
                static fn (): SerieMedia => new SerieMedia(),
                $serie->addMedia(...),
                $serie->removeMedia(...),
            )];

            $this->em->persist($serie);
            $isNew ? $created++ : $updated++;
        }

        $this->em->flush();

        $this->mediaArchiver->restoreFiles($written, $filesDir);

        return ['created' => $created, 'updated' => $updated];
    }

    // The serie's own fields, written in three groups: what it is, who made it, and where it stands
    private function fillSerie(Serie $serie, array $item): void
    {
        $serie
            ->setSlug($item['slug'])
            ->setTitle($item['title'])
            ->setSummary($item['summary'] ?? null)
            ->setKind($item['kind'] ?? null)
            ->setLanguage($item['language'] ?? null);

        $this->fillSerieContributors($serie, $item);
        $this->fillSeriePublication($serie, $item);
    }

    // The two people a serie credits, each with the site they are read on
    private function fillSerieContributors(Serie $serie, array $item): void
    {
        $serie
            ->setAuthor($item['author'] ?? null)
            ->setAuthorWebsite($item['authorWebsite'] ?? null)
            ->setIllustrator($item['illustrator'] ?? null)
            ->setIllustratorWebsite($item['illustratorWebsite'] ?? null);
    }

    // Where the serie stands rather than what it says: a round-trip must not put back on the site what an admin had taken off it
    private function fillSeriePublication(Serie $serie, array $item): void
    {
        $serie
            // Both columns are required, so an archive predating them dates the serie from the import rather than leaving it unwritten
            ->setCreation(isset($item['creation']) ? new \DateTime($item['creation']) : new \DateTime())
            ->setModification(isset($item['modification']) ? new \DateTime($item['modification']) : new \DateTime())
            // Optional like the rest, an archive predating the trash importing as a serie that is not in it - which is what such an archive describes
            ->setIsDeleted($item['isDeleted'] ?? false)
            // Absent from an archive written before the flag existed, and read there as "shown"
            ->setHidden($item['hidden'] ?? false);
    }

    // Existing Blocks have no natural key to match the imported ones against, so the whole collection is replaced - BlockRemovalListener removes the orphaned rows (and their Medias) on flush, same as PageImportProvider
    private function replaceBlocks(Serie $serie, array $blocksData, ?string $filesDir): void
    {
        foreach ($serie->getBlocks()->toArray() as $existingBlock) {
            $serie->removeBlock($existingBlock);
        }

        foreach ($this->blockDataImporter->buildBlocks($blocksData, $filesDir) as $block) {
            $serie->addBlock($block);
        }
    }
}
