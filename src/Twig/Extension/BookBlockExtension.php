<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Twig\Extension;

use c975L\BookBundle\Service\BookServiceInterface;
use c975L\BookBundle\Service\SerieServiceInterface;
use c975L\BookBundle\Service\StripServiceInterface;
use Twig\Attribute\AsTwigFunction;

class BookBlockExtension
{
    public function __construct(
        private readonly BookServiceInterface $bookService,
        private readonly SerieServiceInterface $serieService,
        private readonly StripServiceInterface $stripService,
    ) {
    }

    #[AsTwigFunction('book_block_series')]
    public function getSeries(?int $max = null): array
    {
        return $this->serieService->findAll($max);
    }

    #[AsTwigFunction('book_block_books')]
    public function getBooks(?int $max = null): array
    {
        return $this->bookService->findAllPublished($max);
    }

    #[AsTwigFunction('book_block_to_be_published')]
    public function getToBePublished(): array
    {
        return $this->bookService->findAllToBePublished();
    }

    #[AsTwigFunction('book_block_serie_strips')]
    public function getSerieStrips(string $serieSlug, ?int $max = null): array
    {
        $serie = $this->serieService->findOneBySlugWithSortedBooks($serieSlug);
        if (!$serie) {
            return [];
        }

        return $this->stripService->findAllPublishedBySerie($serie, $max);
    }
}
