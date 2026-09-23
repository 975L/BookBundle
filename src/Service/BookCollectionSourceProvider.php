<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Twig\BookTranslationExtension;
use c975L\UiBundle\Contract\CollectionSourceProviderInterface;
use c975L\UiBundle\Model\CollectionItem;

// Exposes the catalog to UiBundle's "collection" block, so a home page rail follows every publication on its own. One source per language on top of the whole catalog: a book and its translation are two rows, and a rail showing both reads as a bug to a visitor looking for their own language
class BookCollectionSourceProvider implements CollectionSourceProviderInterface
{
    // The rail draws the cover in portrait beside nothing else, which the built-in card does not - Book:Card draws exactly that
    private const string ITEM_TEMPLATE = '@c975LBook/collection/BookItem.html.twig';

    public function __construct(
        private readonly BookServiceInterface $bookService,
        private readonly BookTranslator $bookTranslator,
        private readonly BookRepository $bookRepository,
    ) {
    }

    // Tagged with the catalog, which BookCacheInvalidationListener invalidates on any book saved: each card is then one cache entry, the book it draws never stored with it. No tag while a book waits for a date still ahead, the same veto BookBlockCacheTagProvider applies: no event fires the day it comes out, and a cached listing would leave it out
    public function getSources(): array
    {
        $cacheTags = $this->bookRepository->hasScheduled() ? [] : [BookBlockCacheInvalidator::CACHE_TAG_CATALOG];

        $sources = [
            'book.collection.books' => [
                'label' => 'Livres',
                'count' => $this->bookService->countPublished(...),
                'items' => fn (?int $limit): array => $this->buildItems($limit, null),
                'itemTemplate' => self::ITEM_TEMPLATE,
                'cacheTags' => $cacheTags,
            ],
        ];

        // Read from the catalog itself rather than from a setting: a site publishing in one language gets one extra source, and translating a book adds the source that goes with it without anything to declare
        foreach ($this->languages() as $language) {
            $sources['book.collection.books_' . $language] = [
                'label' => 'Livres (' . BookTranslationExtension::languageLabel($language) . ')',
                'count' => fn (): int => $this->bookService->countPublished($language),
                'items' => fn (?int $limit): array => $this->buildItems($limit, $language),
                'itemTemplate' => self::ITEM_TEMPLATE,
                'cacheTags' => $cacheTags,
            ];
        }

        return $sources;
    }

    // The sources are listed by a back-office form as readily as by a rendered page, and a database not migrated yet must not take that form down with it
    /** @return list<string> */
    private function languages(): array
    {
        try {
            return $this->bookService->findLanguages();
        } catch (\Throwable) {
            return [];
        }
    }

    // An array and not a generator, whatever the interface's "iterable" says: CollectionSourceRegistry::items() is typed "array" and fatals on a Generator
    /** @return list<CollectionItem> */
    private function buildItems(?int $limit, ?string $language): array
    {
        $books = $this->bookService->findAllPublished($limit, $language);

        // The title and the summary are read off the book here and copied into the item, so the language has to be laid on before that and not by whatever renders the rail (see BookTranslator::apply)
        $this->bookTranslator->apply($books);

        return array_map($this->buildItem(...), $books);
    }

    // The book itself travels in "data": BookItem.html.twig hands it to Book:Card, which reads the cover, the summary and the language off it
    private function buildItem(Book $book): CollectionItem
    {
        return new CollectionItem(
            title: $book->getTitle() ?? '',
            description: $book->getSummary(),
            slug: $book->getSlug(),
            data: ['book' => $book],
        );
    }
}
