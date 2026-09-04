<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

use Symfony\Contracts\Cache\TagAwareCacheInterface;

// The tag every cached block of this bundle carries, and the one place it is dropped from. UiBundle's BlockCacheInvalidationListener only ever invalidates the changed Block itself, and knows nothing of the catalog those blocks query at render time - the same gap ShopBundle closes for its own kinds
class BookBlockCacheInvalidator
{
    // One tag for the whole catalog rather than one per kind: a book, a serie, a planche and the person signing them are read by the same five listings, and a finer tag would still have to be dropped wholesale on a rename - a block storing no id of what it shows, nothing finer could be built anyway
    public const string CACHE_TAG_CATALOG = 'book_catalog';

    public function __construct(private readonly TagAwareCacheInterface $cache)
    {
    }

    public function invalidateCatalog(): void
    {
        $this->cache->invalidateTags([self::CACHE_TAG_CATALOG]);
    }
}
