<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Contract\BookCatalogProviderInterface;
use c975L\BookBundle\Entity\Book;
use c975L\ConfigBundle\Service\ConfigServiceInterface;

// The one site's answer, not a merge of several: which rows the catalog page lists is one decision, and two providers answering it would leave the page listing whichever was registered first without saying so
class BookCatalogRegistry
{
    /** @param iterable<BookCatalogProviderInterface> $providers */
    public function __construct(
        private readonly iterable $providers,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    // The books the site lists, null for a site declaring none - the page then reads the bundle's own published books
    /** @return list<Book>|null */
    public function getBooks(): ?array
    {
        foreach ($this->providers as $provider) {
            $books = $provider->getBooks();

            if (null !== $books) {
                return $books;
            }
        }

        return null;
    }

    // The word the site gives its catalog, null for a site keeping the bundle's own. Read from the back office when no provider answers: renaming "Livres" into "Les histoires" is a word and not a rule, and it must not ask for a class
    public function getTitle(): ?string
    {
        foreach ($this->providers as $provider) {
            $title = $provider->getTitle();

            if (null !== $title && '' !== $title) {
                return $title;
            }
        }

        $configured = $this->configService->get('book-catalog-title');

        return \is_string($configured) && '' !== $configured ? $configured : null;
    }
}
