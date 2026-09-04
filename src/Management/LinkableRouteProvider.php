<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Management;

use c975L\BookBundle\Repository\BookCategoryRepository;
use c975L\BookBundle\Repository\ContributorRepository;
use c975L\BookBundle\Repository\SerieRepository;
use c975L\BookBundle\Routing\BookRoutePrefix;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\ConfigBundle\Management\LinkableRouteProviderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Exposes the catalog's public pages as SiteBundle Menu targets (navbar/footer): the indexes, and one entry per serie and per category - a publisher's navbar usually names a collection rather than the whole catalog. Nothing is stored but the target itself: the url is generated at render time (see MenuExtension), so renaming a route prefix or a serie's slug leaves no menu item behind
class LinkableRouteProvider implements LinkableRouteProviderInterface
{
    // What a serie entry is keyed on, its id following - the menu item stores it as "route:book_serie.12"
    public const SERIE_PREFIX = 'book_serie.';

    // The same, for a person - the menu item stores it as "route:book_contributor.3"
    public const CONTRIBUTOR_PREFIX = 'book_contributor.';

    // And for a category - the menu item stores it as "route:book_category.5"
    public const CATEGORY_PREFIX = 'book_category.';

    public function __construct(
        private readonly BookRoutePrefix $routePrefix,
        private readonly BookCategoryRepository $categoryRepository,
        private readonly ContributorRepository $contributorRepository,
        private readonly SerieRepository $serieRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // Only what this site serves: a family whose url prefix is left empty has no public page here (see BookRoutePrefix), and offering it as a menu target would put a dead link in a navbar
    public function getLinkableRoutes(): array
    {
        $routes = [];

        if ($this->routePrefix->isEnabled('book-route-books')) {
            $routes['book_index'] = [
                'label' => 'label.books',
                'translation_domain' => 'book',
            ];
        }

        if ($this->routePrefix->isEnabled('book-route-series')) {
            $routes['serie_index'] = [
                'label' => 'label.series',
                'translation_domain' => 'book',
            ];
        }

        if ($this->routePrefix->isEnabled('book-route-strips')) {
            $routes['strip_index'] = [
                'label' => 'label.strips_series',
                'translation_domain' => 'book',
            ];
        }

        if ($this->routePrefix->isEnabled('book-route-categories')) {
            $routes['book_category_index'] = [
                'label' => 'label.categories',
                'translation_domain' => 'book',
            ];
        }

        if ($this->routePrefix->isEnabled('book-route-contributors')) {
            $routes['contributor_index'] = [
                'label' => 'label.contributors',
                'translation_domain' => 'book',
            ];
        }

        $routes = [...$routes, ...$this->contributorRoutes(), ...$this->categoryRoutes()];

        $serie = $this->translator->trans('label.serie', [], 'book');

        // Every serie the two indexes list, alphabetically as the repository returns them - each below the index listing its kind, and left out when that index is not served here (see BookPublicUrlResolver::serieRoute()). Keyed by id rather than by slug: a renamed serie keeps the menu item pointing at it, its slug and its title both being read here again at each render
        foreach ($this->serieRepository->findAll() as $entity) {
            if (!$this->routePrefix->isEnabled($entity->isStripSerie() ? 'book-route-strips' : 'book-route-series')) {
                continue;
            }

            $routes[self::SERIE_PREFIX . $entity->getId()] = [
                // The title is the publisher's own, not a key to translate - shown as it is in the rendered menu, where "Série - " would only take room in a navbar
                'label' => (string) $entity->getTitle(),
                'translation_domain' => false,
                // The picker holds it among every page of the site, so it says what it is there, and the series sit together once the list is sorted
                'picker_label' => $serie . ' - ' . $entity->getTitle(),
                'route' => BookPublicUrlResolver::serieRoute($entity),
                'params' => ['slug' => (string) $entity->getSlug()],
            ];
        }

        return $routes;
    }

    // Every category the index lists, each below the very segment of that index and left out when it is not served here (see BookRoutePrefix). Keyed by id rather than by slug: a renamed category keeps the menu item pointing at it, its slug and its title both being read here again at each render
    /** @return array<string, array<string, mixed>> */
    private function categoryRoutes(): array
    {
        if (!$this->routePrefix->isEnabled('book-route-categories')) {
            return [];
        }

        $routes = [];
        $category = $this->translator->trans('label.category', [], 'book');

        foreach ($this->categoryRepository->findAll() as $entity) {
            $routes[self::CATEGORY_PREFIX . $entity->getId()] = [
                // The title is the publisher's own, not a key to translate - shown as it is in the rendered menu
                'label' => (string) $entity->getTitle(),
                'translation_domain' => false,
                // The picker holds it among every page of the site, so it says what it is there, and the categories sit together once the list is sorted
                'picker_label' => $category . ' - ' . $entity->getTitle(),
                'route' => 'book_category_display',
                'params' => ['slug' => (string) $entity->getSlug()],
            ];
        }

        return $routes;
    }

    // Every person the index lists, each below their own segment and left out when that segment is not served here (see BookRoutePrefix). Keyed by id rather than by slug: a renamed person keeps the menu item pointing at them, their slug and their name both being read here again at each render
    /** @return array<string, array<string, mixed>> */
    private function contributorRoutes(): array
    {
        if (!$this->routePrefix->isEnabled('book-route-contributor')) {
            return [];
        }

        $routes = [];
        $contributor = $this->translator->trans('label.contributor', [], 'book');

        foreach ($this->contributorRepository->findAll() as $entity) {
            $routes[self::CONTRIBUTOR_PREFIX . $entity->getId()] = [
                // Their own name, not a key to translate - shown as it is in the rendered menu
                'label' => (string) $entity->getName(),
                'translation_domain' => false,
                // The picker holds it among every page of the site, so it says what it is there, and the people sit together once the list is sorted
                'picker_label' => $contributor . ' - ' . $entity->getName(),
                'route' => 'contributor_display',
                'params' => ['slug' => (string) $entity->getSlug()],
            ];
        }

        return $routes;
    }
}
