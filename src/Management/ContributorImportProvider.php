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
use c975L\BookBundle\Entity\ContributorMedia;
use c975L\BookBundle\Repository\ContributorRepository;
use c975L\ConfigBundle\Management\ImportProviderInterface;
use c975L\UiBundle\Management\BlockDataImporter;
use Doctrine\ORM\EntityManagerInterface;

// Imports a "book_contributor" content export (see ContributorExportProvider) - matches by slug, which is what a person answers at, and never by the exported id
// A row created on the fly by ContributorResolver, when a book naming someone was imported first, is filled in here rather than doubled: the two match on the same slug
class ContributorImportProvider implements ImportProviderInterface
{
    public const KIND = 'book_contributor';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ContributorRepository $contributorRepository,
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
            // By slug first, then by the name a shell created by ContributorResolver carries: a book imported before its author gave them a row already, and a second one would split their page in two
            $contributor = $this->contributorRepository->findOneBy(['slug' => $item['slug']]) ?? $this->findShell($item['name']);
            $isNew = null === $contributor;
            $contributor ??= new Contributor();

            $this->fillContributor($contributor, $item);

            $this->replaceBlocks($contributor, $item['blocks'] ?? [], $filesDir);

            $written = [...$written, ...$this->mediaArchiver->sync(
                $contributor->getMedias(),
                $item['medias'] ?? [],
                static fn (): ContributorMedia => new ContributorMedia(),
                $contributor->addMedia(...),
                $contributor->removeMedia(...),
            )];

            $this->em->persist($contributor);
            $isNew ? $created++ : $updated++;
        }

        $this->em->flush();

        $this->mediaArchiver->restoreFiles($written, $filesDir);

        return ['created' => $created, 'updated' => $updated];
    }

    // The row ContributorResolver leaves behind carries nothing but a name and a slug: someone the site has actually written up is a namesake and not that shell, so the archive gets a row of its own rather than overwriting their page
    private function findShell(string $name): ?Contributor
    {
        $found = $this->contributorRepository->findOneByName($name);

        return null !== $found && null === $found->getSummary() && $found->getBlocks()->isEmpty() && $found->getMedias()->isEmpty() ? $found : null;
    }

    // Who they are, then where they stand: a round-trip must not put back on the site someone an admin had taken off it
    private function fillContributor(Contributor $contributor, array $item): void
    {
        $contributor
            ->setSlug($item['slug'])
            ->setName($item['name'])
            ->setSummary($item['summary'] ?? null)
            ->setWebsite($item['website'] ?? null)
            ->setPosition($item['position'] ?? 0)
            // Both columns are required, so an archive predating them dates the person from the import rather than leaving it unwritten
            ->setCreation(isset($item['creation']) ? new \DateTime($item['creation']) : new \DateTime())
            ->setModification(isset($item['modification']) ? new \DateTime($item['modification']) : new \DateTime())
            // Optional like the rest, an archive predating the trash importing as someone who is not in it - which is what such an archive describes
            ->setIsDeleted($item['isDeleted'] ?? false)
            // Absent from an archive written before the flag existed, and read there as "shown"
            ->setHidden($item['hidden'] ?? false);
    }

    // Existing Blocks have no natural key to match the imported ones against, so the whole collection is replaced - BlockRemovalListener removes the orphaned rows (and their Medias) on flush, same as SerieImportProvider
    private function replaceBlocks(Contributor $contributor, array $blocksData, ?string $filesDir): void
    {
        foreach ($contributor->getBlocks()->toArray() as $existingBlock) {
            $contributor->removeBlock($existingBlock);
        }

        foreach ($this->blockDataImporter->buildBlocks($blocksData, $filesDir) as $block) {
            $contributor->addBlock($block);
        }
    }
}
