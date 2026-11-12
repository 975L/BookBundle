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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The serie a book or a strip names in an archive, matched by slug and created on the fly when this environment doesn't hold it yet - same auto-creation as SiteBundle's CollectionGroupResolver, and what lets the three kinds of this bundle be imported in any order: a serie arriving after the books that name it fills in the shell they created rather than a second row
class SerieResolver
{
    // What named a serie created set aside in this run, keyed by its slug, for the word the import ends on (see reportWaiting())
    /** @var array<string, list<string>> */
    private array $waiting = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SerieRepository $serieRepository,
        private readonly ?RequestStack $requestStack = null,
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    // The books and strips waiting for their serie's own import, said once on the screen the import was sent from, and forgotten - nothing for a run with no session (a command)
    public function reportWaiting(): void
    {
        $waiting = $this->waiting;
        $this->waiting = [];
        $session = $this->requestStack?->getCurrentRequest()?->hasSession() ? $this->requestStack->getSession() : null;
        if ([] === $waiting || !$session instanceof FlashBagAwareSessionInterface || null === $this->translator) {
            return;
        }

        $lines = [];
        foreach ($waiting as $slug => $titles) {
            $lines[] = $slug . ' : ' . implode(', ', $titles);
        }

        // Escaped here, EasyAdmin printing flashes raw: the titles come out of an archive
        $session->getFlashBag()->add('warning', htmlspecialchars($this->translator->trans('flash.import_waiting_serie', ['%list%' => implode(' ; ', $lines)], 'book'), \ENT_QUOTES));
    }

    // $resolved is carried through one import, keyed by slug: findOneBy() can't see a serie persisted but not yet flushed, so two books of the same absent serie would otherwise each create their own
    /** @param array<string, Serie> $resolved */
    public function resolve(?string $slug, ?string $title, array &$resolved, ?string $namedBy = null): ?Serie
    {
        if (null === $slug || '' === $slug) {
            return null;
        }

        if (isset($resolved[$slug])) {
            return $this->noteWaiting($slug, $resolved[$slug], $namedBy);
        }

        $serie = $this->serieRepository->findOneBy(['slug' => $slug]);
        if (null === $serie) {
            // The title falls back on the slug for an archive that doesn't carry one, the column being required - whatever the serie's own kind imports later writes over it. Set aside meanwhile: a shell with no cover nor summary stays off the index, the sitemap and the menus until SerieImportProvider brings the real one, shown as its archive says
            $serie = new Serie()->setSlug($slug)->setTitle($title ?? $slug)->setHidden(true)->setCreation(new \DateTime())->setModification(new \DateTime());
            $this->em->persist($serie);
        }

        return $this->noteWaiting($slug, $resolved[$slug] = $serie, $namedBy);
    }

    // A shell created in this run is what a book or a strip now waits on, set aside with it until the serie's own archive brings it
    private function noteWaiting(string $slug, Serie $serie, ?string $namedBy): Serie
    {
        if (null === $serie->getId() && $serie->isHidden() && null !== $namedBy) {
            $this->waiting[$slug][] = $namedBy;
        }

        return $serie;
    }
}
