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
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Service\BookSnippetBuilder;
use Twig\Attribute\AsTwigFunction;

// A Twig function rather than a template of the bundle, on the model of UiBundle's contact_json_ld(): the markup belongs to the bundle, the theme to the site, and a site overriding book/display.html.twig keeps its structured data by calling the same function
class BookJsonLdExtension
{
    public function __construct(private readonly BookSnippetBuilder $snippetBuilder)
    {
    }

    // Returns the <script type="application/ld+json"> payload for a book's page, empty when there is nothing to publish
    #[AsTwigFunction('book_json_ld', isSafe: ['html'])]
    public function bookJsonLd(Book $book, ?string $imageUrl = null, ?string $url = null): string
    {
        return $this->snippetBuilder->buildJson($this->snippetBuilder->buildBook($book, $imageUrl, $url));
    }

    // Same for a serie's page, whose graph carries its volumes
    #[AsTwigFunction('serie_json_ld', isSafe: ['html'])]
    public function serieJsonLd(Serie $serie, ?string $imageUrl = null, ?string $url = null): string
    {
        return $this->snippetBuilder->buildJson($this->snippetBuilder->buildSerie($serie, $imageUrl, $url));
    }

    // Same for a strip's page, empty for one not published yet
    #[AsTwigFunction('strip_json_ld', isSafe: ['html'])]
    public function stripJsonLd(Strip $strip, ?string $imageUrl = null, ?string $url = null): string
    {
        return $this->snippetBuilder->buildJson($this->snippetBuilder->buildStrip($strip, $imageUrl, $url));
    }
}
