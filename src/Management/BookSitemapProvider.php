<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Management;

use c975L\BookBundle\Service\BookCategoryServiceInterface;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\BookServiceInterface;
use c975L\BookBundle\Service\BookTranslatedLocales;
use c975L\BookBundle\Service\ContributorServiceInterface;
use c975L\BookBundle\Service\SerieServiceInterface;
use c975L\BookBundle\Service\StripServiceInterface;
use c975L\ConfigBundle\Management\SitemapProviderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Declares the books/series/strips (public/sitemap-book.xml) - BookBundle's contribution to the site's sitemap-index.xml, collected like any other bundle's by ConfigBundle's SitemapWriter (c975l:sitemaps:create). The bundle supplies urls and nothing else: rendering and writing the file is the writer's job, as it is for SiteBundle's own pages. Only the families this site actually serves: a prefix left empty takes its pages off the site (see BookRoutePrefix), and a sitemap declaring them would advertise urls the router answers nothing for - BookPublicUrlResolver hands back no url for those, index included
class BookSitemapProvider implements SitemapProviderInterface
{
    public function __construct(
        private readonly BookPublicUrlResolver $bookPublicUrlResolver,
        private readonly BookTranslatedLocales $translatedLocales,
        private readonly BookCategoryServiceInterface $categoryService,
        private readonly BookServiceInterface $bookService,
        private readonly ContributorServiceInterface $contributorService,
        private readonly SerieServiceInterface $serieService,
        private readonly StripServiceInterface $stripService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getSitemapName(): string
    {
        return 'book';
    }

    // Every url is built through BookPublicUrlResolver, so the sitemap declares the exact same paths the routes produce - and nothing at all before "site-url" is configured, nor for a family read elsewhere. "title" and "description" are what ConfigBundle's SeoFilesWriter builds public/llms.txt from, the sitemap itself ignoring both. The three indexes, the books and the series carry them: an untitled url is skipped there, and a strip page holds a drawing whose own title says nothing a reader of llms.txt could act on - listing one line per plate would turn the file into a Markdown sitemap, which the format isn't
    public function getUrls(): array
    {
        return array_merge(
            $this->getBookUrls(),
            $this->getCategoryUrls(),
            $this->getSerieUrls(),
            $this->getStripUrls(),
            $this->getContributorUrls(),
        );
    }

    private function getBookUrls(): array
    {
        $urls = $this->getIndexUrls('book_index', 'label.books');

        foreach ($this->bookService->findAllOnline() as $book) {
            $parameters = ['slug' => $book->getSlug()];
            $url = $this->bookPublicUrlResolver->resolve('book_display', $parameters);
            if (null === $url) {
                continue;
            }

            $urls = [...$urls, ...$this->localized([
                'lastmod' => date('Y-m-d', $book->getModification()->getTimestamp()),
                'changefreq' => 'monthly',
                'priority' => 8,
                'title' => (string) $book->getTitle(),
                // The back-cover text, handed over as it stands: the writer flattens it, strips its markup and bounds it
                'description' => $book->getSummary(),
            ], $url, $this->bookPublicUrlResolver->resolveAlternates('book_display', $parameters, $this->translatedLocales->forEntry()))];
        }

        return $urls;
    }

    // The categories a visitor can open, their index above them: one holding nothing the site shows is off the index and off the sitemap alike (see BookCategoryRepository::findWithBooks())
    private function getCategoryUrls(): array
    {
        $urls = $this->getIndexUrls('book_category_index', 'label.categories');

        foreach ($this->categoryService->findWithBooks() as $category) {
            $parameters = ['slug' => $category->getSlug()];
            $url = $this->bookPublicUrlResolver->resolve('book_category_display', $parameters);
            if (null === $url) {
                continue;
            }

            $urls = [...$urls, ...$this->localized([
                'lastmod' => date('Y-m-d', $category->getModification()->getTimestamp()),
                'changefreq' => 'monthly',
                'priority' => 7,
                'title' => (string) $category->getTitle(),
                'description' => $category->getSummary(),
            ], $url, $this->bookPublicUrlResolver->resolveAlternates('book_category_display', $parameters, $this->translatedLocales->forEntry()))];
        }

        return $urls;
    }

    private function getSerieUrls(): array
    {
        $urls = $this->getIndexUrls('serie_index', 'label.series');

        foreach ($this->serieService->findAll() as $serie) {
            // Each serie under the index listing it, the two families having their own segment (see BookPublicUrlResolver::serieRoute())
            $route = BookPublicUrlResolver::serieRoute($serie);
            $parameters = ['slug' => $serie->getSlug()];
            $url = $this->bookPublicUrlResolver->resolve($route, $parameters);
            if (null === $url) {
                continue;
            }

            $urls = [...$urls, ...$this->localized([
                'lastmod' => date('Y-m-d', $serie->getModification()->getTimestamp()),
                'changefreq' => 'monthly',
                'priority' => 8,
                'title' => (string) $serie->getTitle(),
                'description' => $serie->getSummary(),
            ], $url, $this->bookPublicUrlResolver->resolveAlternates($route, $parameters, $this->translatedLocales->forEntry()))];
        }

        return $urls;
    }

    // The people a shown book or serie credits, their index above them: someone credited nowhere is off the index and off the sitemap alike (see ContributorRepository::findCredited())
    private function getContributorUrls(): array
    {
        $urls = $this->getIndexUrls('contributor_index', 'label.contributors');

        foreach ($this->contributorService->findCredited() as $contributor) {
            $parameters = ['slug' => $contributor->getSlug()];
            $url = $this->bookPublicUrlResolver->resolve('contributor_display', $parameters);
            if (null === $url) {
                continue;
            }

            $urls = [...$urls, ...$this->localized([
                'lastmod' => date('Y-m-d', $contributor->getModification()->getTimestamp()),
                'changefreq' => 'monthly',
                'priority' => 7,
                'title' => (string) $contributor->getName(),
                'description' => $contributor->getSummary(),
            ], $url, $this->bookPublicUrlResolver->resolveAlternates('contributor_display', $parameters, $this->translatedLocales->forEntry()))];
        }

        return $urls;
    }

    private function getStripUrls(): array
    {
        // The index lists the series telling the planches, the entries below being the planches themselves (see StripController::index())
        $urls = $this->getIndexUrls('strip_index', 'label.strips_series');

        foreach ($this->stripService->findAllPublished() as $strip) {
            $parameters = ['slug' => $strip->getSlug()];
            $url = $this->bookPublicUrlResolver->resolve('strip_display', $parameters);
            if (null === $url) {
                continue;
            }

            $urls = [...$urls, ...$this->localized([
                'lastmod' => date('Y-m-d', $strip->getModification()->getTimestamp()),
                'changefreq' => 'monthly',
                'priority' => 6,
            ], $url, $this->bookPublicUrlResolver->resolveAlternates('strip_display', $parameters, $this->translatedLocales->forEntry()))];
        }

        return $urls;
    }

    // The listing page opening each of the three sections, empty when the resolver has no url to give - the entries below are then skipped one by one for the very same reason
    private function getIndexUrls(string $route, string $label): array
    {
        $url = $this->bookPublicUrlResolver->resolve($route);

        return null === $url ? [] : $this->localized([
            'lastmod' => date('Y-m-d'),
            'changefreq' => 'weekly',
            'priority' => 9,
            'title' => $this->translator->trans($label, [], 'book'),
        ], $url, $this->bookPublicUrlResolver->resolveAlternates($route, [], $this->translatedLocales->forIndex()));
    }

    // The same page once per language, each entry carrying the whole group: a language's url is only ever crawled if the sitemap names it, and the group alone leaves the other languages undeclared. The writing language comes first (see SiteLocales::all()), so the entry a single-language site has always had stays byte for byte the first one
    /**
     * @param array<string, mixed>  $url
     * @param array<string, string> $alternates
     *
     * @return list<array<string, mixed>>
     */
    private function localized(array $url, string $canonical, array $alternates): array
    {
        return array_map(
            static fn (string $loc): array => ['loc' => $loc, ...$url, 'alternates' => $alternates],
            [] === $alternates ? [$canonical] : array_values($alternates),
        );
    }
}
