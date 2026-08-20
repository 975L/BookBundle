<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Twig;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFunction;

// Which sections a display page actually holds, in the order it lays them out. Read twice by each of those pages - once to build the summary of anchors (<twig:c975LUi:Text:Toc>), once to decide what to render - so an anchor is never offered for a section the page left out, and a section never rendered without an anchor pointing at it
class BookSectionsExtension
{
    public function __construct(
        private readonly BookCustomizationRegistry $customizationRegistry,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array<int, array{anchor: string, label: string}>
     */
    #[AsTwigFunction('book_sections')]
    public function book(Book $book): array
    {
        // A book is read in its own language, whatever the language the visitor reached it in
        $locale = $book->getLanguage();
        $shops = $book->getPublished() instanceof \DateTimeInterface && $book->getPublished()->format('Ymd') <= date('Ymd');

        return $this->sections([
            'resume' => ['label.summary', '' !== (string) $book->getSummary()],
            'apercu' => ['label.videos', !$book->getVideos()->isEmpty()],
            'extracts' => ['label.extracts', !$book->getMedias()->isEmpty()],
            'shops' => ['label.shops', $shops && [] !== $this->customizationRegistry->getLinksOf($book, 'epub')],
            'podcasts' => ['label.podcasts', [] !== $this->customizationRegistry->getLinksOf($book, 'audio') || [] !== $this->customizationRegistry->getLinksOf($book, 'podcast')],
            'presse' => ['label.presse', !$book->getPresses()->isEmpty()],
            'marketing' => ['label.marketing', !$book->getMarketings()->isEmpty()],
            'serie' => ['label.serie', $book->getSerie() instanceof Serie],
            // Always: a book with no field filled in still says when it comes out and who wrote it
            'informations' => ['label.informations', true],
        ], $locale);
    }

    /**
     * @return array<int, array{anchor: string, label: string}>
     */
    #[AsTwigFunction('serie_sections')]
    public function serie(Serie $serie): array
    {
        // The planches are deliberately not a section of their own: a serie holding hundreds of them is composed with the "book_serie_strips" block, which is where how many to show is decided
        return $this->sections([
            'resume' => ['label.summary', '' !== (string) $serie->getSummary()],
            'books' => ['label.serie_books', !$serie->getBooks()->isEmpty()],
        ], $serie->getLanguage());
    }

    /**
     * @param array<string, array{0: string, 1: bool}> $candidates
     *
     * @return array<int, array{anchor: string, label: string}>
     */
    private function sections(array $candidates, ?string $locale): array
    {
        $sections = [];

        foreach ($candidates as $anchor => [$key, $isPresent]) {
            if ($isPresent) {
                $sections[] = ['anchor' => $anchor, 'label' => $this->translator->trans($key, [], 'book', $locale)];
            }
        }

        return $sections;
    }
}
