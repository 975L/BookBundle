<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Routing;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Bundle\FrameworkBundle\Routing\Attribute\AsRoutingConditionService;

// The first segment of each public route, edited in the back office and read at each request rather than baked into the router's cache - a site serves the catalog in its own language ("livres", "books", "libros"), and the change applies straight away with no cache to clear
// Same shape as GalleryBundle's own GalleryRoutePrefix, with one difference that matters: an empty value here turns the pair off instead of falling back to a default. A site whose books are read under its own routes - as numbered "histoires", say - would otherwise serve every book at two addresses competing for the same search result
#[AsRoutingConditionService(alias: self::ALIAS)]
class BookRoutePrefix
{
    public const string ALIAS = 'book_route_prefix';

    // slug of the ConfigBundle entry => name of the route parameter carrying it. One pair per family, the index and the page of a single entry being named apart ("/livres" and "/livre/{slug}") - except the series, read under the very segment of the index listing them ("/series/{slug}", "/strips/{slug}"), so a serie's page sits below its own index rather than beside it (see SerieController). The last two are not segments but short links - one letter and a number, "/b3", the way a book is handed over on a social network
    public const array PREFIXES = [
        'book-route-books' => 'books_prefix',
        'book-route-book' => 'book_prefix',
        'book-route-series' => 'series_prefix',
        'book-route-contributors' => 'contributors_prefix',
        'book-route-contributor' => 'contributor_prefix',
        'book-route-strips' => 'strips_prefix',
        'book-route-strip' => 'strip_prefix',
        'book-route-book-shortcut' => 'book_shortcut',
        'book-route-strip-shortcut' => 'strip_shortcut',
    ];

    public function __construct(
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    // Empty when the entry is unset or blank, which is what a site says when its books are read elsewhere - the routes then match nothing at all
    public function get(string $slug): string
    {
        return trim((string) $this->configService->get($slug), " \t\n\r\0\x0B/");
    }

    // What the routes' condition calls: any other first segment simply doesn't match, and the router carries on with the rest of the site's routes - without that check, "/{book_prefix}/{slug}" would swallow every two-segment url of the site
    public function matches(string $slug, mixed $segment): bool
    {
        $prefix = $this->get($slug);

        return '' !== $prefix && is_string($segment) && $segment === $prefix;
    }

    // Whether a family is served here at all, which the sitemap and the menu targets ask before offering its pages
    public function isEnabled(string $slug): bool
    {
        return '' !== $this->get($slug);
    }
}
