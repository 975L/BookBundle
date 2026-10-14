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
use c975L\BookBundle\Service\BookFavoriteItemProvider;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Service\PlayService;
use Twig\Attribute\AsTwigFunction;

// How many times a book was heard to its end, on the site and in its apps (see UiBundle's PlayTotal), for its pages to say so
class BookPlaysExtension
{
    public function __construct(
        private readonly PlayService $playService,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    // The count, or null below the site's "book-plays-minimum": a handful of listens says less for a story than no figure at all. Read when the page is rendered, so a page the site caches shows it as it stood then
    #[AsTwigFunction('book_plays')]
    public function plays(Book $book): ?int
    {
        if (null === $book->getId()) {
            return null;
        }

        return $this->shown($this->playService->totals(BookFavoriteItemProvider::OWNER_TYPE, [$book->getId()])[$book->getId()] ?? 0);
    }

    // A count as the site lets it be said, null below its "book-plays-minimum" - the rule the pages and ListenJsonController share
    public function shown(int $plays): ?int
    {
        return $plays >= max(1, (int) $this->configService->get('book-plays-minimum')) ? $plays : null;
    }
}
