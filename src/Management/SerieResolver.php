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
use Doctrine\ORM\EntityManagerInterface;

// The serie a book or a strip names in an archive, matched by slug and created on the fly when this environment doesn't hold it yet - same auto-creation as SiteBundle's CollectionGroupResolver, and what lets the three kinds of this bundle be imported in any order: a serie arriving after the books that name it fills in the shell they created rather than a second row
class SerieResolver
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SerieRepository $serieRepository,
    ) {
    }

    // $resolved is carried through one import, keyed by slug: findOneBy() can't see a serie persisted but not yet flushed, so two books of the same absent serie would otherwise each create their own
    /** @param array<string, Serie> $resolved */
    public function resolve(?string $slug, ?string $title, array &$resolved): ?Serie
    {
        if (null === $slug || '' === $slug) {
            return null;
        }

        if (isset($resolved[$slug])) {
            return $resolved[$slug];
        }

        $serie = $this->serieRepository->findOneBy(['slug' => $slug]);
        if (null === $serie) {
            // The title falls back on the slug for an archive that doesn't carry one, the column being required - whatever the serie's own kind imports later writes over it
            $serie = new Serie()->setSlug($slug)->setTitle($title ?? $slug)->setCreation(new \DateTime())->setModification(new \DateTime());
            $this->em->persist($serie);
        }

        return $resolved[$slug] = $serie;
    }
}
