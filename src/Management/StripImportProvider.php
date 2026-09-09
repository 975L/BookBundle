<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Management;

use c975L\BookBundle\Entity\Character;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Entity\StripMedia;
use c975L\BookBundle\Repository\StripRepository;
use c975L\ConfigBundle\Management\ImportProviderInterface;
use c975L\UiBundle\Management\BlockDataImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

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
        private readonly SluggerInterface $slugger,
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
            ->setSummary($item['summary'] ?? null)
            ->setSourceUrl($item['sourceUrl'] ?? null);

        $this->fillStripPublication($strip, $item);

        $strip->setSerie($this->serieResolver->resolve($item['serie'] ?? null, $item['serieTitle'] ?? null, $series));

        // After the serie, which is who the characters are looked for in
        $this->fillStripCharacters($strip, $item);
    }

    // Who speaks, matched by slug against the serie's own people - and written as a bare character where the serie carries no such slug, so an archive from a site that never presented its characters comes in whole rather than losing who spoke in it. The name is the slug until an editor types a better one
    private function fillStripCharacters(Strip $strip, array $item): void
    {
        foreach ($strip->getCharacters() as $character) {
            $strip->removeCharacter($character);
        }

        $serie = $strip->getSerie();

        if (null === $serie) {
            return;
        }

        foreach ($this->charactersRead($item) as $slug => $name) {
            $character = $serie->getCharacter($slug);

            if (null === $character) {
                $character = new Character()->setName($name)->setSlug($slug);
                $serie->addCharacter($character);
            }

            $strip->addCharacter($character);
        }
    }

    // Slug pointing at the name to fall back on: an archive written before a character was a row of its own carries the comma-separated names a planche typed, a current one the serie's own slugs
    private function charactersRead(array $item): array
    {
        $characters = $item['characters'] ?? [];
        $names = [];

        if (\is_string($characters)) {
            foreach (explode(',', $characters) as $name) {
                $name = trim($name);

                if ('' !== $name) {
                    $names[$this->slugger->slug($name)->lower()->toString()] = $name;
                }
            }

            return $names;
        }

        foreach ($characters as $slug) {
            $slug = trim((string) $slug);

            if ('' !== $slug) {
                $names[$slug] = $slug;
            }
        }

        return $names;
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
