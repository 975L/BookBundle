<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Management;

use c975L\BookBundle\Routing\BookRoutePrefix;
use c975L\ConfigBundle\Management\UrlMetadataProviderInterface;

// Lists the catalog's four indexes in "Descriptions d'urls" (c975l:url-metadata:sync), the only pages of this bundle no entity speaks for - a book, a serie, a person and a planche each say their own from their columns, and a row written for them would never be read (see UrlMetadataResolver)
class UrlMetadataProvider implements UrlMetadataProviderInterface
{
    // One listing per family, named by the ConfigBundle entry carrying its first segment. The pages of a single entry are left out: they are the entities above, and the series are read under the very segment of the index listing them (see BookRoutePrefix::PREFIXES)
    private const array LISTINGS = [
        'book-route-books',
        'book-route-series',
        'book-route-categories',
        'book-route-contributors',
        'book-route-strips',
    ];

    public function __construct(
        // The same service the routes match on, so a site serving its catalog under its own words - "histoires", "books", "libros" - gets those urls declared and not the default ones
        private readonly BookRoutePrefix $routePrefix,
    ) {
    }

    // A family whose prefix the site emptied serves no page at all, so nothing is declared for it - the prefixes are read at sync time, and a renamed one simply adds the new url and reports the former as orphaned, for the back office to remove by hand
    public function getUrlMetadataPaths(): array
    {
        $paths = [];

        foreach (self::LISTINGS as $slug) {
            if ($this->routePrefix->isEnabled($slug)) {
                $paths[] = '/' . $this->routePrefix->get($slug);
            }
        }

        return $paths;
    }
}
