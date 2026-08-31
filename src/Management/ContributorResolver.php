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
use c975L\BookBundle\Repository\ContributorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

// The person a book or a serie credits in an archive, matched by name and created on the fly when this environment doesn't hold them yet - same auto-creation as SerieResolver, and what lets an archive written before this entity existed be imported unchanged: it carries the name as a plain string, which is exactly what is matched on
// Matched by name and not by slug, on purpose: the name is what the four string columns held, so it is the only key an older archive - and the migration off those columns - has to offer
class ContributorResolver
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ContributorRepository $contributorRepository,
        private readonly SluggerInterface $slugger,
    ) {
    }

    // $resolved is carried through one import, keyed by name: findOneBy() can't see a person persisted but not yet flushed, so two books of the same absent author would otherwise each create their own
    /** @param array<string, Contributor> $resolved */
    public function resolve(?string $name, ?string $website, array &$resolved): ?Contributor
    {
        $name = trim((string) $name);
        if ('' === $name) {
            return null;
        }

        if (isset($resolved[$name])) {
            return $resolved[$name];
        }

        $contributor = $this->contributorRepository->findOneByName($name);
        if (null === $contributor) {
            $contributor = new Contributor()
                ->setName($name)
                ->setSlug($this->uniqueSlug($name, $resolved))
                ->setCreation(new \DateTime())
                ->setModification(new \DateTime())
            ;
            $this->em->persist($contributor);
        }

        // Only ever filled in, never overwritten: an archive naming a site the person already carries says nothing new, and one naming none must not erase what is there
        if (null !== $website && '' !== trim($website) && null === $contributor->getWebsite()) {
            $contributor->setWebsite(trim($website));
        }

        return $resolved[$name] = $contributor;
    }

    // The slug their page answers at, taken from their name and suffixed until nobody else holds it - two people called the same are two pages, not one
    // The rows created earlier in the same run are counted with those in database, for the reason $resolved exists at all: findOneBy() cannot see a row persisted but not yet flushed, and two names slugifying alike ("Jean-Luc Picard" and "Jean Luc Picard") would both take the free slug and trip the unique index on flush
    /** @param array<string, Contributor> $resolved */
    private function uniqueSlug(string $name, array $resolved): string
    {
        $taken = array_filter(array_map(static fn (Contributor $c): ?string => $c->getSlug(), $resolved));
        $base = strtolower($this->slugger->slug($name)->toString());
        $slug = $base;
        $suffix = 1;

        while (in_array($slug, $taken, true) || null !== $this->contributorRepository->findOneBy(['slug' => $slug])) {
            $slug = $base . '-' . ++$suffix;
        }

        return $slug;
    }
}
