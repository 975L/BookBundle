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
use c975L\ConfigBundle\Service\LocalizedUrlGenerator;
use c975L\ConfigBundle\Service\SiteLocales;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// Builds the canonical public url of an index, a book, a serie or a strip - the single place this bundle's public urls are spelled, so BookSitemapProvider (and anything checking those urls later) can't drift from the routes themselves. Same shape as SiteBundle's PagePublicUrlResolver, with a route name instead of an entity: three families share these routes where a Page has but one. The path is generated through the router rather than hand-built, so a renamed route can never leave a stale url behind in the sitemap. The configured prefix is passed to the generator here instead of being read from the request context (BookRoutePrefixListener's job): this runs from a cron command, where there is no request to carry it - and the host comes from "site-url" for that very same reason
class BookPublicUrlResolver
{
    // Public route => the ConfigBundle entry carrying its first segment, the route parameter it fills being BookRoutePrefix::PREFIXES's own. Public: BookLinkLocalizer recognises this bundle's own urls by the very same map, and a copy of it there would be a second place to keep a route in
    public const array ROUTE_PREFIXES = [
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
        private readonly LocalizedUrlGenerator $localizedUrlGenerator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly SiteLocales $siteLocales,
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

    // The same path in the language the page around it is read in, which is what a template links with - resolvePath() staying the canonical url a sitemap, a hreflang group and a JSON-LD node declare (see LocalizedUrlGenerator::path)
    /** @param array<string, mixed> $parameters */
    public function resolveLocalizedPath(string $route, array $parameters = []): ?string
    {
        $slug = self::ROUTE_PREFIXES[$route] ?? throw new \InvalidArgumentException(sprintf('The route "%s" is not one of this bundle\'s public routes.', $route));
        $prefix = $this->routePrefix->get($slug);
        if ('' === $prefix) {
            return null;
        }

        // The prefix is passed rather than left to the request context, exactly as above: one rule for the two, and nothing to remember about which of them may run without a request
        return $this->localizedUrlGenerator->path($route, [BookRoutePrefix::PREFIXES[$slug] => $prefix, ...$parameters]);
    }

    // One absolute url per language the page answers in, for a sitemap's alternates - empty wherever a group would name itself alone, as PagePublicUrlResolver::alternatesFor(), and asked of the router directly, a cron command holding no request
    /**
     * @param array<string, mixed> $parameters
     * @param list<string>         $locales
     *
     * @return array<string, string> hreflang => absolute url
     */
    public function resolveAlternates(string $route, array $parameters, array $locales): array
    {
        $siteUrl = $this->siteUrl();
        $default = $this->siteLocales->getDefaultLocale();
        if (null === $siteUrl || \count($locales) < 2 || !\in_array($default, $locales, true)) {
            return [];
        }

        $slug = self::ROUTE_PREFIXES[$route] ?? throw new \InvalidArgumentException(sprintf('The route "%s" is not one of this bundle\'s public routes.', $route));
        $prefix = $this->routePrefix->get($slug);
        if ('' === $prefix) {
            return [];
        }

        $parameters = [BookRoutePrefix::PREFIXES[$slug] => $prefix, ...$parameters];

        $alternates = [];
        foreach ($locales as $locale) {
            try {
                $alternates[$locale] = $siteUrl . ($locale === $default
                    ? $this->urlGenerator->generate($route, $parameters)
                    : $this->urlGenerator->generate($route . LocalizedUrlGenerator::LOCALIZED_SUFFIX, $parameters + ['_locale' => $locale]));
            } catch (RoutingExceptionInterface) {
                // No localised twin, or none answering for these parameters: there is no group to declare rather than a sitemap that cannot be written at all
                return [];
            }
        }

        return $alternates;
    }

    // The configured host without its trailing slash, null when unconfigured - every generated path already opens with a slash, and a "site-url" saved as "https://example.com/" would otherwise double it
    private function siteUrl(): ?string
    {
        $siteUrl = trim((string) $this->configService->get('site-url'));

        return '' === $siteUrl ? null : rtrim($siteUrl, '/');
    }
}
