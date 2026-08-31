<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Twig;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use Twig\Attribute\AsTwigFunction;

// The path of one of this bundle's public pages, null for a family this site does not serve. path() cannot answer that: the first segment of every one of those routes is a ConfigBundle entry (see BookRoutePrefix), and generating one whose entry is empty throws rather than returning nothing - which is how a book page belonging to a serie went down on a site serving books alone
class BookUrlExtension
{
    public function __construct(private readonly BookPublicUrlResolver $publicUrlResolver)
    {
    }

    /** @param array<string, mixed> $parameters */
    #[AsTwigFunction('book_path')]
    public function path(string $route, array $parameters = []): ?string
    {
        return $this->publicUrlResolver->resolvePath($route, $parameters);
    }

    // The path of a serie's page, below the index listing its kind (see BookPublicUrlResolver::serieRoute()): the templates hand the serie over and never have to tell which of the two routes reads it
    /** @param array<string, mixed> $parameters */
    #[AsTwigFunction('serie_path')]
    public function seriePath(Serie $serie, array $parameters = []): ?string
    {
        return $this->publicUrlResolver->resolvePath(
            BookPublicUrlResolver::serieRoute($serie),
            ['slug' => (string) $serie->getSlug(), ...$parameters]
        );
    }

    // The path of a person's page, the templates handing the person over rather than spelling their slug
    #[AsTwigFunction('contributor_path')]
    public function contributorPath(Contributor $contributor): ?string
    {
        return $this->publicUrlResolver->resolvePath('contributor_display', ['slug' => (string) $contributor->getSlug()]);
    }

    // The same page as an absolute url, needed wherever a path says nothing - a share tag, a structured-data node
    #[AsTwigFunction('contributor_url')]
    public function contributorUrl(Contributor $contributor): ?string
    {
        return $this->publicUrlResolver->resolve('contributor_display', ['slug' => (string) $contributor->getSlug()]);
    }

    // The same page as an absolute url, which the JSON-LD of a serie's page needs where a path says nothing
    #[AsTwigFunction('serie_url')]
    public function serieUrl(Serie $serie): ?string
    {
        return $this->publicUrlResolver->resolve(
            BookPublicUrlResolver::serieRoute($serie),
            ['slug' => (string) $serie->getSlug()]
        );
    }

    // The url of every language the same book is written in, its own included, for the "alternate hreflang" links of the layout. Absolute urls, built from "site-url" like the canonical they sit next to: a hreflang group is read from another site than the one serving it, and a relative path says nothing there
    /** @return array<string, string> */
    #[AsTwigFunction('book_alternates')]
    public function alternates(Book $book): array
    {
        $alternates = [];
        foreach ([$book, ...BookTranslationExtension::translations($book)] as $other) {
            $language = $other->getLanguage();
            $url = null === $language ? null : $this->publicUrlResolver->resolve('book_display', ['slug' => $other->getSlug()]);
            if (null !== $url) {
                $alternates[$language] = $url;
            }
        }

        // A group not naming the page it is written on is invalid, and one naming it alone repeats what the canonical already said: a book without a translation declares nothing
        return count($alternates) > 1 && isset($alternates[(string) $book->getLanguage()]) ? $alternates : [];
    }
}
