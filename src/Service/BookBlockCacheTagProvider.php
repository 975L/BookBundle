<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Repository\StripRepository;
use c975L\UiBundle\Contract\BlockCacheTagProviderInterface;
use c975L\UiBundle\Entity\Block;
use Symfony\Contracts\Service\ResetInterface;

// Every kind of this bundle resolves its content live at render time (see BookBlockExtension), which no Block/Media event ever signals a change of - so each entry carries the catalog tag BookCacheInvalidationListener drops whenever a book, a serie, a planche, a contributor or one of their medias changes. That is what lets those kinds be cached at all rather than declared "cacheable: false", the same way ShopBundle's own are
class BookBlockCacheTagProvider implements BlockCacheTagProviderInterface, ResetInterface
{
    // The kinds listing what is out today, which is a question of the hour it is asked at: their queries read "published <= now" (see BookRepository::publishedQueryBuilder and findAllToBePublished), and a book dated next week moves from the second listing to the first with nobody saving anything
    private const array BOOK_KINDS = [
        'book_books',
        'book_to_be_published',
    ];

    // The kinds reading no date at all: a serie is listed as long as it is neither trashed nor set aside, a category as long as it holds a book the site shows, a person as long as they are credited on something (see SerieRepository::findAll, BookCategoryRepository::findWithBooks and ContributorRepository::findCredited). Their entry only ever goes stale on a row being saved, which the tag covers - the draw at random set aside, see resolveCatalog()
    private const array DATELESS_KINDS = [
        'book_series',
        'book_categories',
        'book_contributors',
    ];

    // Read at most once per request: a page carrying three book blocks asks the same question three times on a cold cache, and the answer cannot change inside one render
    private ?bool $scheduledBook = null;

    private ?bool $scheduledStrip = null;

    public function __construct(
        private readonly BookRepository $bookRepository,
        private readonly StripRepository $stripRepository,
    ) {
    }

    public function getCacheTagResolvers(): array
    {
        $resolvers = [];

        foreach (self::DATELESS_KINDS as $kind) {
            $resolvers[$kind] = $this->resolveCatalog(...);
        }

        foreach (self::BOOK_KINDS as $kind) {
            $resolvers[$kind] = $this->resolveBooks(...);
        }

        $resolvers['book_serie_strips'] = $this->resolveStrips(...);

        return $resolvers;
    }

    // Dropped between two requests, a worker runtime (FrankenPHP, RoadRunner...) keeping this service alive from one to the next - a book coming out would otherwise stay "scheduled" for as long as the process lives
    public function reset(): void
    {
        $this->scheduledBook = null;
        $this->scheduledStrip = null;
    }

    // The kinds reading no date: nothing but the draw can make their entry go stale
    /**
     * @return string[]|null
     */
    private function resolveCatalog(Block $block): ?array
    {
        return $this->random($block) ? null : [BookBlockCacheInvalidator::CACHE_TAG_CATALOG];
    }

    // Null, i.e. render this block live, for as long as a book waits for a date still ahead: a cache entry never expires (see BookExtension, $item->expiresAfter(null)) and no event fires the day that date comes round, so the entry would announce a book as "to be published" long after it came out. Nothing scheduled, nothing to go stale: the listing is then cached like any other, and putting a date on a book is a save, which drops the tag and puts this very question back
    /**
     * @return string[]|null
     */
    private function resolveBooks(Block $block): ?array
    {
        if ($this->random($block)) {
            return null;
        }

        return ($this->scheduledBook ??= $this->bookRepository->hasScheduled())
            ? null
            : [BookBlockCacheInvalidator::CACHE_TAG_CATALOG];
    }

    // The same, for the planches: a serie publishing one a week always has the next one dated ahead, which is exactly the case a frozen entry would hide until something else was saved
    /**
     * @return string[]|null
     */
    private function resolveStrips(Block $block): ?array
    {
        if ($this->random($block)) {
            return null;
        }

        return ($this->scheduledStrip ??= $this->stripRepository->hasScheduled())
            ? null
            : [BookBlockCacheInvalidator::CACHE_TAG_CATALOG];
    }

    // A listing drawing its rows at random is rendered live too: a cached entry would freeze one single draw until the catalog itself changed, which is the very thing the option asks not to happen (the same veto ShopBlockCacheTagProvider applies to a shop listing)
    private function random(Block $block): bool
    {
        return true === ($block->getData()['random'] ?? false);
    }
}
