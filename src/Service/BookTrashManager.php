<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Contract\TrashableInterface;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\ConfigBundle\Entity\Redirect;
use c975L\ConfigBundle\Repository\RedirectRepository;
use c975L\UiBundle\Repository\RatingRepository;
use c975L\UiBundle\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;

// What the three CRUD screens do to a row that leaves the site, and to the url it used to answer. A deletion only takes the row off the site (see moveToTrash()), where removing it for good leaves a permanent 410 behind, and a renamed row keeps its old url answering with a 301 - all of it through ConfigBundle's own Redirect rows, which are what the site already serves redirects and gone urls from (see RedirectSubscriber)
class BookTrashManager
{
    public function __construct(
        private readonly BookPublicUrlResolver $publicUrlResolver,
        private readonly EntityManagerInterface $entityManager,
        private readonly RedirectRepository $redirectRepository,
        private readonly RatingRepository $ratingRepository,
        private readonly ReviewRepository $reviewRepository,
    ) {
    }

    // Takes the row off the site without losing anything of it: its files, its blocks and everything hanging off it stay as they are, and its page answers 410 for as long as it can still be restored (see the display() of each public controller)
    public function moveToTrash(TrashableInterface $entity): void
    {
        $entity
            ->setIsDeleted(true)
            ->setModification(new \DateTime());

        $this->entityManager->flush();
    }

    // Brings the row back to the site exactly as it left it
    public function restore(TrashableInterface $entity): void
    {
        $entity
            ->setIsDeleted(false)
            ->setModification(new \DateTime());

        $this->entityManager->flush();
    }

    // Removes the row for good, its url left answering 410 rather than falling back to a plain 404: the 410 the trash served only lasts as long as the row can still be restored, where a "gone" Redirect keeps answering it for good - which search engines act on far faster. Nothing is written for a site that reads this family elsewhere (an empty prefix, so no url of ours to speak of), nor where a redirect already covers that path: a target an admin set up deliberately says more than a dead end
    public function deletePermanently(TrashableInterface $entity, string $route): void
    {
        $path = $this->publicPath($entity, $route);

        if (null !== $path) {
            // Redirects pointing at this row would otherwise dangle - turned into "gone" rows rather than deleted, their own urls leading to content that is now just as removed
            foreach ($this->redirectRepository->findByToUrl($path) as $redirect) {
                $redirect->setGone(true)->setToUrl(null);
            }

            if (null === $this->redirectRepository->findOneByFromPath($path)) {
                $this->entityManager->persist(new Redirect()->setFromPath($path)->setGone(true));
            }
        }

        // The only rows pointing at a book that Doctrine doesn't take with it: a translation names its original through a column carrying neither cascade nor "on delete", so removing the original with a translation still naming it fails on the foreign key. Everything else a book, a serie or a strip holds is cascaded or removed as an orphan
        if ($entity instanceof Book) {
            foreach ($entity->getTranslations() as $translation) {
                $translation->setTranslationBook(null);
            }

            // The earlier version becomes again what nothing replaces, so a book the catalog lists: the column is "SET NULL", but the loaded object would keep the reference until the next load (see Book::$newerVersion)
            $entity->getPreviousVersion()?->setNewerVersion(null);
        }

        // The readers' votes and what they wrote, which hang off a name and an id rather than off a relation (see c975L\UiBundle\Entity\Rating and Review) and so are cascaded by nothing. Removed here and not in moveToTrash(): a row put in the trash can still come back, and it must find its notes and its reviews where it left them
        $ownerType = match (true) {
            $entity instanceof Book => 'book',
            $entity instanceof Strip => 'strip',
            $entity instanceof Serie => 'serie',
            default => null,
        };

        if (null !== $ownerType && null !== $entity->getId()) {
            $this->ratingRepository->deleteForOwner($ownerType, $entity->getId());
            $this->reviewRepository->deleteForOwner($ownerType, $entity->getId());
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    // A renamed row keeps its old url answering: a 301 to the new one, which is what a visitor's bookmark and a search engine's index both hold. Persisted, not flushed - the caller is the CRUD's own updateEntity(), whose flush covers it
    public function redirectSlugChange(string $route, string $oldSlug, string $newSlug): void
    {
        $fromPath = $this->publicUrlResolver->resolvePath($route, ['slug' => $oldSlug]);
        $toUrl = $this->publicUrlResolver->resolvePath($route, ['slug' => $newSlug]);

        if (null === $fromPath || null === $toUrl) {
            return;
        }

        // A redirect starting from the new slug would turn the pair into a loop - renaming "tome-1" to "tome-2" and back leaves the two rows pointing at each other
        $reverseRedirect = $this->redirectRepository->findOneByFromPath($toUrl);
        if (null !== $reverseRedirect) {
            $this->entityManager->remove($reverseRedirect);
        }

        // Whatever already sat on the old path is turned into that redirect, a row declaring it gone included - the opposite of what deletePermanently() does with a row an admin wrote, and for the opposite reason: there the content is really gone, where here it is alive at a new address, and sending a reader to it beats telling them it no longer exists
        $redirect = $this->redirectRepository->findOneByFromPath($fromPath)
            ?? new Redirect()->setFromPath($fromPath);

        $redirect
            ->setToUrl($toUrl)
            ->setGone(false)
            ->setPermanent(true);

        $this->entityManager->persist($redirect);
    }

    // The path the row answers on this site, null where the family is served elsewhere or the row never got a slug (see BookPublicUrlResolver)
    private function publicPath(TrashableInterface $entity, string $route): ?string
    {
        $slug = $entity->getSlug();

        return null === $slug || '' === $slug ? null : $this->publicUrlResolver->resolvePath($route, ['slug' => $slug]);
    }
}
