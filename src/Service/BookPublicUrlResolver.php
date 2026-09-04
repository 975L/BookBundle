<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Routing\BookRoutePrefix;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// Builds the canonical public url of an index, a book, a serie or a strip - the single place this bundle's public urls are spelled, so BookSitemapProvider (and anything checking those urls later) can't drift from the routes themselves. Same shape as SiteBundle's PagePublicUrlResolver, with a route name instead of an entity: three families share these routes where a Page has but one. The path is generated through the router rather than hand-built, so a renamed route can never leave a stale url behind in the sitemap. The configured prefix is passed to the generator here instead of being read from the request context (BookRoutePrefixListener's job): this runs from a cron command, where there is no request to carry it - and the host comes from "site-url" for that very same reason
class BookPublicUrlResolver
{
    // Public route => the ConfigBundle entry carrying its first segment, the route parameter it fills being BookRoutePrefix::PREFIXES's own
    private const array ROUTE_PREFIXES = [
        'book_index' => 'book-route-books',
        'book_display' => 'book-route-book',
        'book_category_index' => 'book-route-categories',
        'book_category_display' => 'book-route-categories',
        'contributor_index' => 'book-route-contributors',
        'contributor_display' => 'book-route-contributor',
        'serie_index' => 'book-route-series',
        'serie_display' => 'book-route-series',
        'strip_index' => 'book-route-strips',
        'strip_serie_display' => 'book-route-strips',
        'strip_display' => 'book-route-strip',
    ];

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly BookRoutePrefix $routePrefix,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    // Which of the two routes reads a serie, the one below the index listing its kind (see SerieKind): said once here rather than at each of the dozen places generating that url
    public static function serieRoute(Serie $serie): string
    {
        return $serie->isStripSerie() ? 'strip_serie_display' : 'serie_display';
    }

    // Null when "site-url" isn't configured yet, a sitemap accepting no relative url, and null too for a family this site doesn't serve
    public function resolve(string $route, array $parameters = []): ?string
    {
        $siteUrl = $this->siteUrl();
        $path = $this->resolvePath($route, $parameters);

        return null === $siteUrl || null === $path ? null : $siteUrl . $path;
    }

    // The local part of that url, without the host - null when the family's prefix is empty, which is how a site says its books are read elsewhere (see BookRoutePrefix): the routes then match nothing, and an url pointing at them would be a dead link
    public function resolvePath(string $route, array $parameters = []): ?string
    {
        $slug = self::ROUTE_PREFIXES[$route] ?? throw new \InvalidArgumentException(sprintf('The route "%s" is not one of this bundle\'s public routes.', $route));
        $prefix = $this->routePrefix->get($slug);
        if ('' === $prefix) {
            return null;
        }

        return $this->urlGenerator->generate(
            $route,
            [BookRoutePrefix::PREFIXES[$slug] => $prefix, ...$parameters],
            UrlGeneratorInterface::ABSOLUTE_PATH
        );
    }

    // The configured host without its trailing slash, null when unconfigured - every generated path already opens with a slash, and a "site-url" saved as "https://example.com/" would otherwise double it
    private function siteUrl(): ?string
    {
        $siteUrl = trim((string) $this->configService->get('site-url'));

        return '' === $siteUrl ? null : rtrim($siteUrl, '/');
    }
}
