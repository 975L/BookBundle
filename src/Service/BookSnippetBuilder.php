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
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Enum\BookContributorRole;
use c975L\UiBundle\Service\RatingSnippetBuilder;

// Builds the schema.org graph a book's, a serie's or a strip's page publishes as JSON-LD, out of the fields those pages already show. Assembled here rather than as microdata on the rendered elements, for the same reason as UiBundle's ContactSnippetBuilder: an itemprop pinned to an element leaves an empty node behind when the field is empty, where a graph simply drops what wasn't filled in - and it can carry what no template displays (the two ISBNs as two editions, the rank of a volume in its serie). Price and availability are deliberately absent: they are an "offers" node, which belongs to whoever sells the book - emitted twice, they would diverge.
class BookSnippetBuilder
{
    // What schema.org calls the edition a kind names. A kind is the site's own word (see c975L\BookBundle\Contract\BookCustomizationProviderInterface), so it is matched on rather than mapped: any kind holding "paper" is a paperback, one holding "audio" an audiobook, and anything else - an epub, a pdf, a web edition - an ebook
    private const array BOOK_FORMATS = [
        'paper' => 'Paperback',
        'audio' => 'AudiobookFormat',
    ];

    public function __construct(
        private readonly BookPublicUrlResolver $publicUrlResolver,
        private readonly RatingSnippetBuilder $ratingSnippetBuilder,
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
            'author' => $this->person($book->getEffectiveAuthor()),
            'illustrator' => $this->person($book->getEffectiveIllustrator()),
            // The one part of a book's credits schema.org names on a CreativeWork: the voice reading it is "readBy", which belongs to an Audiobook and not to the Book node this is (see BookContributorRole), so it stays on the page and out of the graph
            'translator' => $this->persons($book->getContributorsOf(BookContributorRole::Translator->value)),
            'inLanguage' => trim((string) $book->getLanguage()),
            'datePublished' => $book->getPublished()?->format('Y-m-d') ?? '',
            'bookFormat' => $this->bookFormat($book),
            'typicalAgeRange' => trim((string) $book->getAge()),
            // What the book is about, said with the word schema.org has for it - the categories the site shows, a hidden one being off the site and out of the graph with it
            'genre' => $this->genres($book),
            // One workExample per edition rather than a single "isbn": a paperback and an ebook are two editions of the same work, and schema.org has no way to say which of two ISBNs belongs to which
            'workExample' => $this->editions($book),
            // The same work in another language, said as a pair rather than as two unrelated books: a translation is not an edition, and carries neither the ISBNs nor the pages of the one it translates
            'translationOfWork' => $this->translation($book->getTranslationBook()),
            'workTranslation' => $this->translations($book),
            // The version this one replaces - the text before it was illustrated, revised or recomposed. Said one way only, schema.org having no inverse of "isBasedOn": the old one says nothing of the new, which is exactly what lets an engine tell them apart (see Book::$newerVersion)
            'isBasedOn' => $this->translation($book->getPreviousVersion()),
            'isPartOf' => $this->partOfSerie($book->getSerie()),
            'position' => $this->positionInSerie($book),
            // The tally the page already shows above its title, said in the graph too - what puts the stars in a search result. Empty while nobody has voted, an AggregateRating over no vote being what Google rejects the whole rich result for (see UiBundle's RatingSnippetBuilder)
            'aggregateRating' => $this->rating('book', $book->getId()),
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
            'author' => $this->person($serie->getAuthor()),
            'illustrator' => $this->person($serie->getIllustrator()),
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
            'author' => $this->person($serie?->getAuthor()),
            'illustrator' => $this->person($serie?->getIllustrator()),
            'inLanguage' => trim((string) $serie?->getLanguage()),
            'datePublished' => $published->format('Y-m-d'),
            'position' => $strip->getNumber() ?? 0,
            'isPartOf' => $this->partOfSerie($serie),
            'character' => $this->characters($strip),
            // Where the strip first appeared, which is the same work at another address rather than a work this one derives from
            'sameAs' => trim((string) $strip->getSourceUrl()),
        ]);
    }

    // The person a page is about, as the entity a search engine files them under: what they signed is published by the books themselves, each naming its author, so their own page states who they are and where else they are found rather than repeating a catalog
    public function buildContributor(Contributor $contributor, ?string $imageUrl = null, ?string $url = null): array
    {
        $name = trim((string) $contributor->getName());

        if ('' === $name) {
            return [];
        }

        return $this->clean([
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $name,
            'url' => trim((string) $url),
            'description' => $this->plainText($contributor->getSummary()),
            'image' => trim((string) $imageUrl),
            // Their own site, which is what tells two people of the same name apart
            'sameAs' => trim((string) $contributor->getWebsite()),
        ]);
    }

    // The trail leading to the page, as the BreadcrumbList a search engine prints in place of the raw url: the levels are handed over already resolved, names translated and urls depending on prefixes a site may empty (see Breadcrumb.html.twig)
    /** @param list<array{name: string, url: string}> $trail the levels in reading order, the page's own included */
    public function buildBreadcrumb(array $trail): array
    {
        $elements = [];
        $position = 0;

        foreach ($trail as $level) {
            $name = trim($level['name']);
            $url = trim($level['url']);

            // A level with nothing to show is dropped rather than numbered: a list whose positions skip one is a malformed breadcrumb
            if ('' === $name || '' === $url) {
                continue;
            }

            $elements[] = [
                '@type' => 'ListItem',
                'position' => ++$position,
                'name' => $name,
                'item' => $url,
            ];
        }

        // A single level is the page itself: a trail leading nowhere says nothing a url does not already say
        if (\count($elements) < 2) {
            return [];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    // What a listing holds, as the ItemList a search engine reads a page of cards through.
    /**
     * @param list<array{name: string, url: string}> $items  the cards in reading order
     * @param int                                    $offset how many the pages before this one already listed, a listing growing on scroll numbering its cards from where the last one stopped
     */
    public function buildItemList(array $items, int $offset = 0): array
    {
        $elements = [];
        $position = max(0, $offset);

        foreach ($items as $item) {
            $name = trim($item['name']);
            $url = trim($item['url']);

            if ('' === $name || '' === $url) {
                continue;
            }

            $elements[] = [
                '@type' => 'ListItem',
                'position' => ++$position,
                'name' => $name,
                'url' => $url,
            ];
        }

        if ([] === $elements) {
            return [];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            // What this page holds and not what the whole catalog does: a count claiming more than the elements below it is what a validator refuses
            'numberOfItems' => \count($elements),
            'itemListElement' => $elements,
        ];
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

    // The votes cast on one item, as the node schema.org reads them - the tally the widget displays, through the builder UiBundle already owns so the two never disagree
    /** @return array<string, mixed> */
    private function rating(string $ownerType, ?int $ownerId): array
    {
        return null === $ownerId ? [] : $this->ratingSnippetBuilder->build($ownerType, $ownerId);
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

    // A named person, with their site as their identity when they have one - a bare name would leave two authors called the same indistinguishable. The categories the book carries, as plain names: schema.org's "genre" takes a text or an url, and a category page of ours is a listing rather than a thing to point at
    /**
     * @return list<string>
     */
    private function genres(Book $book): array
    {
        $genres = [];

        foreach ($book->getShownCategories() as $category) {
            $title = trim((string) $category->getTitle());
            if ('' !== $title) {
                $genres[] = $title;
            }
        }

        return $genres;
    }

    private function person(?Contributor $contributor): array
    {
        $name = trim((string) $contributor?->getName());
        if ('' === $name) {
            return [];
        }

        return $this->clean([
            '@type' => 'Person',
            'name' => $name,
            'url' => trim((string) $contributor?->getWebsite()),
        ]);
    }

    // Several named people, for a part more than one person can take - a book carried into another language by two hands is credited to both
    /**
     * @param list<Contributor> $contributors
     *
     * @return list<array<string, string>>
     */
    private function persons(array $contributors): array
    {
        $persons = [];

        foreach ($contributors as $contributor) {
            $person = $this->person($contributor);
            if ([] !== $person) {
                $persons[] = $person;
            }
        }

        return $persons;
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
