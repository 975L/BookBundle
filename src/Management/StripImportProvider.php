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
use c975L\BookBundle\Entity\StripMedia;
use c975L\BookBundle\Repository\StripRepository;
use c975L\ConfigBundle\Management\ImportProviderInterface;
use c975L\UiBundle\Management\BlockDataImporter;
use Doctrine\ORM\EntityManagerInterface;

// Imports a "book_strip" content export (see StripExportProvider) - matches by slug, which is what a strip answers at, and never by the exported id
class StripImportProvider implements ImportProviderInterface
{
    public const KIND = 'book_strip';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly StripRepository $stripRepository,
        private readonly BlockDataImporter $blockDataImporter,
        private readonly MediaArchiver $mediaArchiver,
        private readonly SerieResolver $serieResolver,
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
        $series = [];

        foreach ($items as $item) {
            $strip = $this->stripRepository->findOneBy(['slug' => $item['slug']]);
            $isNew = null === $strip;
            $strip ??= new Strip();

            $this->fillStrip($strip, $item, $series);

            $this->replaceBlocks($strip, $item['blocks'] ?? [], $filesDir);

            $written = [...$written, ...$this->mediaArchiver->sync(
                $strip->getMedias(),
                $item['medias'] ?? [],
                static fn (): StripMedia => new StripMedia(),
                $strip->addMedia(...),
                $strip->removeMedia(...),
            )];

            $this->em->persist($strip);
            $isNew ? $created++ : $updated++;
        }

        $this->em->flush();

        $this->mediaArchiver->restoreFiles($written, $filesDir);

        return ['created' => $created, 'updated' => $updated];
    }

    // The strip's own fields, what it holds written apart from where it stands
    /** @param array<string, \c975L\BookBundle\Entity\Serie> $series */
    private function fillStrip(Strip $strip, array $item, array &$series): void
    {
        $strip
            ->setSlug($item['slug'])
            ->setTitle($item['title'])
            ->setNumber($item['number'] ?? null)
            // charactersSlug follows from it, so nothing to import beside it (see Strip::setCharacters)
            ->setCharacters($item['characters'] ?? null)
            ->setSummary($item['summary'] ?? null)
            ->setSourceUrl($item['sourceUrl'] ?? null);

        $this->fillStripPublication($strip, $item);

        $strip->setSerie($this->serieResolver->resolve($item['serie'] ?? null, $item['serieTitle'] ?? null, $series));
    }

    // The dates and the two flags, read back for the reason they are exported: a round-trip must not put back on the site what an admin had taken off it
    private function fillStripPublication(Strip $strip, array $item): void
    {
        $strip
            ->setPublished(isset($item['published']) ? new \DateTime($item['published']) : null)
            // Both columns are required, so an archive predating them dates the strip from the import rather than leaving it unwritten
            ->setCreation(isset($item['creation']) ? new \DateTime($item['creation']) : new \DateTime())
            ->setModification(isset($item['modification']) ? new \DateTime($item['modification']) : new \DateTime())
            ->setIsDeleted($item['isDeleted'] ?? false)
            // Absent from an archive written before the flag existed, and read there as "shown"
            ->setHidden($item['hidden'] ?? false);
    }

    // Existing Blocks have no natural key to match the imported ones against, so the whole collection is replaced - BlockRemovalListener removes the orphaned rows (and their Medias) on flush, same as PageImportProvider
    private function replaceBlocks(Strip $strip, array $blocksData, ?string $filesDir): void
    {
        foreach ($strip->getBlocks()->toArray() as $existingBlock) {
            $strip->removeBlock($existingBlock);
        }

        foreach ($this->blockDataImporter->buildBlocks($blocksData, $filesDir) as $block) {
            $strip->addBlock($block);
        }
    }
}
