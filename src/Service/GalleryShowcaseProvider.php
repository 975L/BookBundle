<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Entity\StripMedia;
use c975L\UiBundle\Contract\GalleryShowcaseProviderInterface;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

// Shows all 4 of BookBundle's block kinds in a block gallery/showcase (see UiBundle's GalleryShowcaseRegistry, consumed by the public block showcase). None fit BlockFixtureProviderInterface: their templates all resolve real content live via book_block_*() (BookBlockExtension), querying Book/Serie/Strip straight from the database. Rendered here instead, directly against the same underlying components with in-memory (never persisted) sample entities, bypassing those queries.
class GalleryShowcaseProvider implements GalleryShowcaseProviderInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
        private readonly PlaceholderMediaRegistry $placeholderMediaRegistry,
    ) {
    }

    public function getShowcases(): array
    {
        return [
            $this->translator->trans('label.gallery_showcase_book_series', [], 'book') => [
                'description' => $this->translator->trans('label.gallery_showcase_book_series_description', [], 'book'),
                'kind' => 'book_series',
                'variants' => ['' => $this->seriesVariant()],
            ],
            $this->translator->trans('label.gallery_showcase_book_books', [], 'book') => [
                'description' => $this->translator->trans('label.gallery_showcase_book_books_description', [], 'book'),
                'kind' => 'book_books',
                'variants' => ['' => $this->booksVariant()],
            ],
            $this->translator->trans('label.gallery_showcase_book_to_be_published', [], 'book') => [
                'description' => $this->translator->trans('label.gallery_showcase_book_to_be_published_description', [], 'book'),
                'kind' => 'book_to_be_published',
                'variants' => ['' => $this->toBePublishedVariant()],
            ],
            $this->translator->trans('label.gallery_showcase_book_serie_strips', [], 'book') => [
                'description' => $this->translator->trans('label.gallery_showcase_book_serie_strips_description', [], 'book'),
                'kind' => 'book_serie_strips',
                'variants' => ['' => $this->serieStripsVariant()],
            ],
        ];
    }

    // Series/Serie.html.twig falls back to a bundled "no-cover.webp" placeholder whenever a Serie has no cover media, so the sample entities below don't need one attached
    private function seriesVariant(): string
    {
        $series = [];
        for ($i = 1; $i <= 2; ++$i) {
            $series[] = new Serie()
                ->setTitle("Série exemple {$i}")
                ->setSlug("serie-exemple-{$i}")
                ->setLanguage('fr');
        }

        return $this->twig->render('@c975LBook/components/Serie/Series.html.twig', [
            'series' => $series,
            'displayMore' => 'true',
        ]);
    }

    // Book/Book.html.twig falls back to the same placeholder cover as Serie's - no media needed either
    private function booksVariant(): string
    {
        $books = [];
        for ($i = 1; $i <= 2; ++$i) {
            $books[] = new Book()
                ->setTitle("Livre exemple {$i}")
                ->setSlug("livre-exemple-{$i}")
                ->setLanguage('fr')
                ->setPublished(new \DateTime('-1 month'));
        }

        return $this->twig->render('@c975LBook/components/Book/Books.html.twig', [
            'books' => $books,
            'displayMore' => 'true',
        ]);
    }

    // "published" left null on purpose: Book/Book.html.twig reads it to display the "à paraître" wording
    private function toBePublishedVariant(): string
    {
        $books = [
            new Book()
                ->setTitle('Prochain livre')
                ->setSlug('prochain-livre')
                ->setLanguage('fr'),
        ];

        return $this->twig->render('@c975LBook/components/Book/ToBePublished.html.twig', [
            'books' => $books,
        ]);
    }

    // A planche is read for its drawing: Strip/Card.html.twig prints no image when a strip has no media, so each sample carries one the app declares (PlaceholderMediaProviderInterface), an app declaring none keeping the bare cards
    private function serieStripsVariant(): string
    {
        $images = $this->placeholderMediaRegistry->getImages();

        $strips = [];
        for ($i = 1; $i <= 3; ++$i) {
            $strip = new Strip()
                ->setTitle("Planche {$i}")
                ->setSlug("planche-{$i}")
                ->setPublished(new \DateTime("-{$i} week"));

            // Rotated so the three planches don't share one photo, as the placeholder pool is rotated everywhere else
            if ([] !== $images) {
                $strip->addMedia(new StripMedia()->setName($images[($i - 1) % \count($images)]));
            }

            $strips[] = $strip;
        }

        return $this->twig->render('@c975LBook/components/Strip/Cards.html.twig', [
            'strips' => $strips,
        ]);
    }
}
