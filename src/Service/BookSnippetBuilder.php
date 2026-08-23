<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookEdition;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;

// Builds the schema.org graph a book's, a serie's or a strip's page publishes as JSON-LD, out of the fields those pages already show.
// Assembled here rather than as microdata on the rendered elements, for the same reason as UiBundle's ContactSnippetBuilder: an itemprop pinned to an element leaves an empty node behind when the field is empty, where a graph simply drops what wasn't filled in - and it can carry what no template displays (the two ISBNs as two editions, the rank of a volume in its serie).
// Price and availability are deliberately absent: they are an "offers" node, which belongs to whoever sells the book (see TODO-ShopBundle.md) - emitted twice, they would diverge.
class BookSnippetBuilder
{
    // What schema.org calls the edition a kind names. A kind is the site's own word (see c975L\BookBundle\Contract\BookCustomizationProviderInterface), so it is matched on rather than mapped: any kind holding "paper" is a paperback, one holding "audio" an audiobook, and anything else - an epub, a pdf, a web edition - an ebook
    private const array BOOK_FORMATS = [
        'paper' => 'Paperback',
        'audio' => 'AudiobookFormat',
    ];

    public function __construct(
        private readonly BookPublicUrlResolver $publicUrlResolver,
    ) {
    }

    // $imageUrl is resolved by the caller rather than read from the entity: only a template can turn an attached Media into an absolute url
    public function buildBook(Book $book, ?string $imageUrl = null, ?string $url = null): array
    {
        $name = trim((string) $book->getTitle());

        // No title, no graph: a book node without one indexes nothing
        if ('' === $name) {
            return [];
        }

        return $this->clean([
            '@context' => 'https://schema.org',
            '@type' => 'Book',
            'name' => $name,
            'url' => trim((string) $url),
            'description' => $this->plainText($book->getSummary()),
            'image' => trim((string) $imageUrl),
            'author' => $this->person($book->getEffectiveAuthor(), $book->getEffectiveAuthorWebsite()),
            'illustrator' => $this->person($book->getEffectiveIllustrator(), $book->getEffectiveIllustratorWebsite()),
            'inLanguage' => trim((string) $book->getLanguage()),
            'datePublished' => $book->getPublished()?->format('Y-m-d') ?? '',
            'bookFormat' => $this->bookFormat($book),
            'typicalAgeRange' => trim((string) $book->getAge()),
            // One workExample per edition rather than a single "isbn": a paperback and an ebook are two editions of the same work, and schema.org has no way to say which of two ISBNs belongs to which
            'workExample' => $this->editions($book),
            // The same work in another language, said as a pair rather than as two unrelated books: a translation is not an edition, and carries neither the ISBNs nor the pages of the one it translates
            'translationOfWork' => $this->translation($book->getTranslationBook()),
            'workTranslation' => $this->translations($book),
            // The version this one replaces - the text before it was illustrated, revised or recomposed. Said one way only, schema.org having no inverse of "isBasedOn": the old one says nothing of the new, which is exactly what lets an engine tell them apart (see Book::$newerVersion)
            'isBasedOn' => $this->translation($book->getPreviousVersion()),
            'isPartOf' => $this->partOfSerie($book->getSerie()),
            'position' => $this->positionInSerie($book),
        ]);
    }

    public function buildSerie(Serie $serie, ?string $imageUrl = null, ?string $url = null): array
    {
        $name = trim((string) $serie->getTitle());

        if ('' === $name) {
            return [];
        }

        return $this->clean([
            '@context' => 'https://schema.org',
            '@type' => 'BookSeries',
            'name' => $name,
            'url' => trim((string) $url),
            'description' => $this->plainText($serie->getSummary()),
            'image' => trim((string) $imageUrl),
            'author' => $this->person($serie->getAuthor(), $serie->getAuthorWebsite()),
            'illustrator' => $this->person($serie->getIllustrator(), $serie->getIllustratorWebsite()),
            'inLanguage' => trim((string) $serie->getLanguage()),
            // The volumes in the order they came out, which is what says "tome 1 of 12" to a machine - a serie page otherwise only lines thumbnails up
            'hasPart' => $this->volumes($serie),
        ]);
    }

    // A strip is a ComicStory, the single page of a serie a reader follows, where a book of that same serie is a Book - the two are read apart and indexed apart
    public function buildStrip(Strip $strip, ?string $imageUrl = null, ?string $url = null): array
    {
        $name = trim((string) $strip->getTitle());
        $published = $strip->getPublished();

        // A strip still to come publishes nothing, its page being a 404 until the day it comes out
        if ('' === $name || null === $published || $published > new \DateTime()) {
            return [];
        }

        $serie = $strip->getSerie();

        return $this->clean([
            '@context' => 'https://schema.org',
            '@type' => 'ComicStory',
            'name' => $name,
            'url' => trim((string) $url),
            'description' => $this->plainText($strip->getSummary()),
            'image' => trim((string) $imageUrl),
            // A strip carries no author of its own, the serie it belongs to naming the one who draws them all
            'author' => $this->person($serie?->getAuthor(), $serie?->getAuthorWebsite()),
            'illustrator' => $this->person($serie?->getIllustrator(), $serie?->getIllustratorWebsite()),
            'inLanguage' => trim((string) $serie?->getLanguage()),
            'datePublished' => $published->format('Y-m-d'),
            'position' => $strip->getNumber() ?? 0,
            'isPartOf' => $this->partOfSerie($serie),
            'character' => $this->characters($strip),
            // Where the strip first appeared, which is the same work at another address rather than a work this one derives from
            'sameAs' => trim((string) $strip->getSourceUrl()),
        ]);
    }

    // The same graph, encoded for a <script type="application/ld+json">; empty string when there is nothing to publish
    public function buildJson(array $snippet): string
    {
        if ([] === $snippet) {
            return '';
        }

        // JSON_HEX_TAG matters: it turns a "</script>" typed into any field into <, which no browser closes the tag on
        return json_encode($snippet, \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    // The characters a strip puts on stage, each named and nothing more - the page linking to their strips says where to find them again
    private function characters(Strip $strip): array
    {
        $characters = [];

        foreach ($strip->getCharactersList() as $character) {
            $characters[] = [
                '@type' => 'Person',
                'name' => $character['name'],
            ];
        }

        return $characters;
    }

    // A named person, with their site as their identity when they have one - a bare string would leave two authors of the same name indistinguishable
    private function person(?string $name, ?string $website): array
    {
        $name = trim((string) $name);
        if ('' === $name) {
            return [];
        }

        return $this->clean([
            '@type' => 'Person',
            'name' => $name,
            'url' => trim((string) $website),
        ]);
    }

    // One node per edition carrying an ISBN, each a Book of its own as schema.org expects of a workExample. An edition not out yet is left out: its ISBN names a book nobody can get
    private function editions(Book $book): array
    {
        $editions = [];

        foreach ($book->getEditions() as $edition) {
            $isbn = trim((string) $edition->getIsbn());

            if ('' === $isbn) {
                continue;
            }

            $format = $this->editionFormat($edition);

            $editions[] = $this->clean([
                // An audiobook is a type of its own, schema.org holding it as much an audio recording as a book to read - the others are a Book carrying the format they came out in
                '@type' => 'AudiobookFormat' === $format ? 'Audiobook' : 'Book',
                'bookFormat' => 'https://schema.org/' . $format,
                'name' => trim((string) $book->getTitle()),
                'isbn' => $isbn,
                'inLanguage' => trim((string) $book->getLanguage()),
                'datePublished' => $book->getPublished()?->format('Y-m-d') ?? '',
                'numberOfPages' => $edition->getPages() ?? 0,
                // The platforms carrying this edition, as identities and not as offers: what it costs and whether it is in stock belong to whoever sells it (see the note at the top)
                'sameAs' => $this->editionLinks($book, $edition),
            ]);
        }

        return $editions;
    }

    // The schema.org format an edition's kind stands for, an ebook being what a kind naming neither paper nor audio describes. A guess, and the only one left here: the vocabulary is the site's own (see BookCustomizationProviderInterface), so a site naming its editions in another language gets EBook - which is what a decorated service of its own overrides
    private function editionFormat(BookEdition $edition): string
    {
        $kind = (string) $edition->getKind();

        foreach (self::BOOK_FORMATS as $needle => $format) {
            if (str_contains($kind, $needle)) {
                return $format;
            }
        }

        return 'EBook';
    }

    // The addresses this edition is found at: the book's own, taken in the gesture the edition serves - a recording is listened to where the podcast apps carry it, a printed or digital book is bought where the bookshops sell it. The platforms belong to the book, an edition saying only what it comes out under (see BookEditionType)
    private function editionLinks(Book $book, BookEdition $edition): array
    {
        $urls = [];
        $links = str_contains((string) $edition->getKind(), 'audio') ? $book->getListenLinks() : $book->getBuyLinks();

        foreach ($links as $link) {
            $url = trim((string) $link->getUrl());

            if ('' !== $url) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    // The translations of a book, each a work of its own at its own address - a reader and a crawler both follow it there
    private function translations(Book $book): array
    {
        $translations = [];

        foreach ($book->getTranslations() as $translation) {
            $node = $this->translation($translation);

            if ([] !== $node) {
                $translations[] = $node;
            }
        }

        return $translations;
    }

    // One translated book as a node, its url generated rather than passed by the caller: the page displaying it knows its own address, not the addresses of the books it points to
    private function translation(?Book $book): array
    {
        $name = trim((string) $book?->getTitle());

        if (null === $book || '' === $name) {
            return [];
        }

        return $this->clean([
            '@type' => 'Book',
            'name' => $name,
            'inLanguage' => trim((string) $book->getLanguage()),
            'url' => (string) $this->publicUrlResolver->resolve('book_display', ['slug' => $book->getSlug()]),
        ]);
    }

    // The format of the book itself, only when it has a single edition - two editions each carry their own (see editions())
    private function bookFormat(Book $book): string
    {
        $editions = $this->editions($book);

        return 1 === \count($editions) ? (string) $editions[0]['bookFormat'] : '';
    }

    // The serie a book or a strip belongs to, as the very node the serie's own page publishes for itself
    private function partOfSerie(?Serie $serie): array
    {
        $name = trim((string) $serie?->getTitle());

        if ('' === $name) {
            return [];
        }

        return [
            '@type' => 'BookSeries',
            'name' => $name,
        ];
    }

    // The volume number the book carries, and failing that its rank among the published volumes of its serie - a number is only ever written on a serie a reader follows in order, and where none is written the order the books came out in is that same order
    private function positionInSerie(Book $book): int
    {
        $serie = $book->getSerie();
        if (null === $serie) {
            return 0;
        }

        if (null !== $book->getNumber()) {
            return $book->getNumber();
        }

        $position = array_search($book, $this->publishedVolumes($serie), true);

        return false === $position ? 0 : $position + 1;
    }

    // The volumes as parts of the serie, each named and linked to nothing else - a machine follows the serie's own page to find them
    private function volumes(Serie $serie): array
    {
        $volumes = [];

        foreach ($this->publishedVolumes($serie) as $rank => $volume) {
            $volumes[] = $this->clean([
                '@type' => 'Book',
                'name' => trim((string) $volume->getTitle()),
                'position' => $rank + 1,
                'datePublished' => $volume->getPublished()?->format('Y-m-d') ?? '',
            ]);
        }

        return $volumes;
    }

    /**
     * @return list<Book> The serie's published books, oldest first
     */
    private function publishedVolumes(Serie $serie): array
    {
        $now = new \DateTime();

        $volumes = array_values(array_filter(
            $serie->getBooks()->toArray(),
            static fn (Book $book) => null !== $book->getPublished() && $book->getPublished() <= $now,
        ));

        usort($volumes, static fn (Book $first, Book $second) => $first->getPublished() <=> $second->getPublished());

        return $volumes;
    }

    // The summary is rich text; a graph carries the words only
    private function plainText(mixed $html): string
    {
        $text = html_entity_decode(strip_tags((string) $html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    // Drops everything left empty, so an unfilled field never reaches the graph as a blank property
    private function clean(array $snippet): array
    {
        return array_filter($snippet, static fn ($value) => !\in_array($value, ['', [], null, 0], true));
    }
}
