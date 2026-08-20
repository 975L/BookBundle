<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Management;

use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\BookServiceInterface;
use c975L\BookBundle\Service\SerieServiceInterface;
use c975L\BookBundle\Service\StripServiceInterface;
use c975L\ConfigBundle\Management\SitemapProviderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Declares the books/series/strips (public/sitemap-book.xml) - BookBundle's contribution to the site's sitemap-index.xml, collected like any other bundle's by ConfigBundle's SitemapWriter (c975l:sitemaps:create). The bundle supplies urls and nothing else: rendering and writing the file is the writer's job, as it is for SiteBundle's own pages
// Only the families this site actually serves: a prefix left empty takes its pages off the site (see BookRoutePrefix), and a sitemap declaring them would advertise urls the router answers nothing for - BookPublicUrlResolver hands back no url for those, index included
class BookSitemapProvider implements SitemapProviderInterface
{
    public function __construct(
        private readonly BookPublicUrlResolver $bookPublicUrlResolver,
        private readonly BookServiceInterface $bookService,
        private readonly SerieServiceInterface $serieService,
        private readonly StripServiceInterface $stripService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getSitemapName(): string
    {
        return 'book';
    }

    // Every url is built through BookPublicUrlResolver, so the sitemap declares the exact same paths the routes produce - and nothing at all before "site-url" is configured, nor for a family read elsewhere
    // "title" and "description" are what ConfigBundle's SeoFilesWriter builds public/llms.txt from, the sitemap itself ignoring both. The three indexes, the books and the series carry them: an untitled url is skipped there, and a strip page holds a drawing whose own title says nothing a reader of llms.txt could act on - listing one line per plate would turn the file into a Markdown sitemap, which the format isn't
    public function getUrls(): array
    {
        return array_merge(
            $this->getBookUrls(),
            $this->getSerieUrls(),
            $this->getStripUrls(),
        );
    }

    private function getBookUrls(): array
    {
        $urls = $this->getIndexUrls('book_index', 'label.books');

        foreach ($this->bookService->findAllPublished() as $book) {
            $url = $this->bookPublicUrlResolver->resolve('book_display', ['slug' => $book->getSlug()]);
            if (null === $url) {
                continue;
            }

            $urls[] = [
                'loc' => $url,
                'lastmod' => date('Y-m-d', $book->getModification()->getTimestamp()),
                'changefreq' => 'monthly',
                'priority' => 8,
                'title' => (string) $book->getTitle(),
                // The back-cover text, handed over as it stands: the writer flattens it, strips its markup and bounds it
                'description' => $book->getSummary(),
            ];
        }

        return $urls;
    }

    private function getSerieUrls(): array
    {
        $urls = $this->getIndexUrls('serie_index', 'label.series');

        foreach ($this->serieService->findAll() as $serie) {
            $url = $this->bookPublicUrlResolver->resolve('serie_display', ['slug' => $serie->getSlug()]);
            if (null === $url) {
                continue;
            }

            $urls[] = [
                'loc' => $url,
                'lastmod' => date('Y-m-d', $serie->getModification()->getTimestamp()),
                'changefreq' => 'monthly',
                'priority' => 8,
                'title' => (string) $serie->getTitle(),
                'description' => $serie->getSummary(),
            ];
        }

        return $urls;
    }

    private function getStripUrls(): array
    {
        $urls = $this->getIndexUrls('strip_index', 'label.strips');

        foreach ($this->stripService->findAllPublished() as $strip) {
            $url = $this->bookPublicUrlResolver->resolve('strip_display', ['slug' => $strip->getSlug()]);
            if (null === $url) {
                continue;
            }

            $urls[] = [
                'loc' => $url,
                'lastmod' => date('Y-m-d', $strip->getModification()->getTimestamp()),
                'changefreq' => 'monthly',
                'priority' => 6,
            ];
        }

        return $urls;
    }

    // The listing page opening each of the three sections, empty when the resolver has no url to give - the entries below are then skipped one by one for the very same reason
    private function getIndexUrls(string $route, string $label): array
    {
        $url = $this->bookPublicUrlResolver->resolve($route);

        return null === $url ? [] : [[
            'loc' => $url,
            'lastmod' => date('Y-m-d'),
            'changefreq' => 'weekly',
            'priority' => 9,
            'title' => $this->translator->trans($label, [], 'book'),
        ]];
    }
}
