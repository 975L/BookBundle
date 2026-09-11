<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Routing\BookRoutePrefix;
use c975L\UiBundle\Contract\InternalLinkLocalizerInterface;

// Rewrites this catalog's own links into the language the page around them is being read in - the counterpart of SiteBundle's PageLinkLocalizer, for the eleven screens this bundle owns. Without it a visitor reading "/en/" is sent back into the writing language at the first click on a cover
class BookLinkLocalizer implements InternalLinkLocalizerInterface
{
    // The routes this bundle answers both bare and localised, and whether a slug follows their first segment - their prefix read off BookPublicUrlResolver::ROUTE_PREFIXES at each call, an editor naming it
    private const array SLUGGED = [
        'book_index' => false,
        'book_display' => true,
        'book_category_index' => false,
        'book_category_display' => true,
        'contributor_index' => false,
        'contributor_display' => true,
        'serie_index' => false,
        'serie_display' => true,
        'strip_index' => false,
        'strip_serie_display' => true,
        'strip_display' => true,
    ];

    public function __construct(
        private readonly BookRoutePrefix $routePrefix,
        private readonly BookPublicUrlResolver $publicUrlResolver,
    ) {
    }

    public function localize(string $value): string
    {
        // A whole rich text: only what an href holds is a link, the same words elsewhere in the prose being prose
        if (str_contains($value, 'href="')) {
            return (string) preg_replace_callback(
                '#href="([^"]*)"#',
                fn (array $matches): string => sprintf('href="%s"', $this->localizePath($matches[1])),
                $value
            );
        }

        return $this->localizePath($value);
    }

    // The resolver holds the whole rule - the prefix handed to the router rather than left to a request, the language being read, the twin, the fallback - so a stored link reads the way a template's own "book_path" does, a block rendered with no request around it included
    private function localizePath(string $path): string
    {
        foreach (self::SLUGGED as $route => $slugged) {
            $prefix = $this->routePrefix->get(BookPublicUrlResolver::ROUTE_PREFIXES[$route]);

            // A family this site does not serve: its routes match nothing, and an url pointing at them would be a dead link (see BookRoutePrefix)
            if ('' === $prefix) {
                continue;
            }

            $pattern = $slugged
                ? sprintf('#^/%s/(?<slug>[a-zA-Z0-9\-]+)$#', preg_quote($prefix, '#'))
                : sprintf('#^/%s$#', preg_quote($prefix, '#'));

            if (1 !== preg_match($pattern, $path, $matches)) {
                continue;
            }

            return (string) $this->publicUrlResolver->resolveLocalizedPath($route, $slugged ? ['slug' => $matches['slug']] : []);
        }

        return $path;
    }
}
