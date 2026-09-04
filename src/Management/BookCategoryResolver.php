<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Management;

use c975L\BookBundle\Entity\BookCategory;
use c975L\BookBundle\Repository\BookCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;

// The categories a book names in an archive, matched by slug and created on the fly when this environment doesn't hold them yet - same auto-creation as SerieResolver, and what lets the two kinds be imported in any order: a category arriving after the books filed under it fills in the shell they created rather than a second row
class BookCategoryResolver
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BookCategoryRepository $categoryRepository,
    ) {
    }

    // $resolved is carried through one import, keyed by slug: findOneBy() can't see a category persisted but not yet flushed, so two books of the same absent category would otherwise each create their own. The slugs come from an archive file and are typed by nobody: anything that is not a name is skipped rather than trusted
    /**
     * @param array<mixed>                $slugs
     * @param array<string, BookCategory> $resolved
     *
     * @return list<BookCategory>
     */
    public function resolveAll(array $slugs, array &$resolved): array
    {
        $categories = [];

        foreach ($slugs as $slug) {
            if (!\is_string($slug) || '' === $slug) {
                continue;
            }

            $categories[] = $resolved[$slug] ??= $this->findOrCreate($slug);
        }

        return $categories;
    }

    private function findOrCreate(string $slug): BookCategory
    {
        $category = $this->categoryRepository->findOneBy(['slug' => $slug]);

        if (null === $category) {
            // The title falls back on the slug for an archive that doesn't carry one, the column being required - whatever the category's own kind imports later writes over it
            $category = new BookCategory()->setSlug($slug)->setTitle($slug)->setCreation(new \DateTime())->setModification(new \DateTime());
            $this->em->persist($category);
        }

        return $category;
    }
}
