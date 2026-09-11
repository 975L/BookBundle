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
use c975L\BookBundle\Entity\BookCategory;
use c975L\BookBundle\Entity\Character;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Media;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\UiBundle\Service\ContentTranslator;

// What this catalog says in another language, one row in every language with its translations beside it as a page's are (see SiteBundle's PageTranslator) - only what a visitor reads, never a slug, an ISBN or a person's own name
class BookTranslator
{
    // The vocabulary this bundle's rows are named with, the way Page and Product name theirs - a plain string, no foreign key ever pointing at it (see UiBundle's Translation)
    public const string OWNER_BOOK = 'book_book';

    public const string OWNER_SERIE = 'book_serie';

    public const string OWNER_CATEGORY = 'book_category';

    public const string OWNER_STRIP = 'book_strip';

    public const string OWNER_CHARACTER = 'book_character';

    public const string OWNER_CONTRIBUTOR = 'book_contributor';

    // A picture's own title, which is what a press cutting and a promotional visual print under themselves (see Book:Presse and Book:Marketing)
    public const string OWNER_MEDIA = 'book_media';

    // What a translation may cover of a book: the title it is read under, and the summary printed under it
    public const array BOOK_FIELDS = ['title', 'summary'];

    public const array SERIE_FIELDS = ['title', 'summary'];

    public const array CATEGORY_FIELDS = ['title', 'summary'];

    public const array STRIP_FIELDS = ['title', 'summary'];

    // A character is renamed by the translation the book was given, and the page reading them follows
    public const array CHARACTER_FIELDS = ['name', 'presentation'];

    // The presentation alone: a person's name is the same in every language, and rewriting an author's name is not translating
    public const array CONTRIBUTOR_FIELDS = ['summary'];

    // What a translation may cover of a picture: the title printed under it, which stands as its alternative text too
    public const array MEDIA_FIELDS = ['title'];

    // The texts a language screen offers as a line rather than as prose: a row's own name, which a listing, a card and a <title> read
    public const array LINE_FIELDS = ['title', 'name'];

    public function __construct(
        private readonly ContentTranslator $contentTranslator,
        private readonly SiteLocales $siteLocales,
    ) {
    }

    public function isActive(): bool
    {
        return $this->contentTranslator->isActive();
    }

    // The languages a row may be written in besides the one it was written in
    /** @return list<string> */
    public function getTranslatableLocales(): array
    {
        return $this->contentTranslator->getTranslatableLocales();
    }

    // Lays the language being rendered over each row's own texts for the render being built, called by whatever renders them rather than on postLoad: the back office has to go on showing the text a row was written in
    /** @param iterable<Book|BookCategory|Character|Contributor|Media|Serie|Strip> $rows */
    public function apply(iterable $rows, ?string $locale = null): void
    {
        // Tested before the collection is touched: on a single-language site the proxy behind it is never initialised, and a listing costs no query at all here
        if (!$this->contentTranslator->isActive()) {
            return;
        }

        $rows = $rows instanceof \Traversable ? iterator_to_array($rows) : $rows;

        if ([] === $rows) {
            return;
        }

        $this->preload($rows, $locale);

        foreach ($rows as $row) {
            $id = $row->getId();
            if (null === $id) {
                continue;
            }

            // Given nothing to lay over, translate() hands back the translated fields alone - an untranslated one is absent rather than null, which is what makes the getters fall back on the text the row was written in
            $row->setTranslated($this->contentTranslator->translate($this->owner($row), $id, [], $this->fields($row), $locale));
        }
    }

    // Reads ahead a whole set of rows, so a catalog of a dozen books costs one query rather than a dozen
    /** @param iterable<Book|BookCategory|Character|Contributor|Media|Serie|Strip> $rows */
    public function preload(iterable $rows, ?string $locale = null): void
    {
        if (!$this->contentTranslator->isActive()) {
            return;
        }

        // Grouped by kind: each is one query of its own, a book and a serie being two owner types
        $ids = [];
        foreach ($rows as $row) {
            $id = $row->getId();
            if (null !== $id) {
                $ids[$this->owner($row)][] = $id;
            }
        }

        foreach ($ids as $owner => $ownerIds) {
            $this->contentTranslator->preload($owner, $ownerIds, $locale);
        }
    }

    // The languages this row really says something in, its own included - the moment its own name does, which url answers being BookTranslatedLocales' question
    /** @return list<string> */
    public function translatedLocales(Book | BookCategory | Character | Contributor | Media | Serie | Strip $row): array
    {
        $id = $row->getId();
        $locales = [$this->siteLocales->getDefaultLocale()];
        if (null === $id) {
            return $locales;
        }

        $name = $this->fields($row)[0];

        foreach ($this->getTranslatableLocales() as $locale) {
            $written = $this->contentTranslator->values($this->owner($row), $id, $locale)[$name] ?? null;
            if (null !== $written && '' !== $written) {
                $locales[] = $locale;
            }
        }

        return $locales;
    }

    // Every language this row has been given, for the screen that writes them
    /** @return array<string, array<string, string|null>> locale => field => value */
    public function all(Book | BookCategory | Character | Contributor | Media | Serie | Strip $row): array
    {
        $id = $row->getId();

        return null === $id ? [] : $this->contentTranslator->all($this->owner($row), $id);
    }

    // What a language screen offers for each translatable text: what that language already says, or the source text between brackets where it says nothing yet
    /** @return array<string, string|null> field => value */
    public function promptValues(Book | BookCategory | Character | Contributor | Media | Serie | Strip $row, string $locale): array
    {
        $written = $this->all($row)[$locale] ?? [];

        $values = [];
        foreach ($this->fields($row) as $field) {
            $translated = $written[$field] ?? null;
            $values[$field] = null !== $translated && '' !== $translated
                ? $translated
                : ContentTranslator::prompt($row->getUntranslated($field));
        }

        return $values;
    }

    // Hands what a language screen wrote over to be stored on the flush that saves the row, a field left holding the bracketed source counting as nothing written (see ContentTranslator::stage)
    /** @param array<string, string|null> $values field => value */
    public function stage(Book | BookCategory | Character | Contributor | Media | Serie | Strip $row, string $locale, array $values): void
    {
        $id = $row->getId();
        if (null === $id) {
            return;
        }

        $staged = [];
        foreach ($this->fields($row) as $field) {
            if (!array_key_exists($field, $values)) {
                continue;
            }

            $staged[$field] = ContentTranslator::untouched($values[$field], $row->getUntranslated($field)) ? null : $values[$field];
        }

        if ([] !== $staged) {
            $this->contentTranslator->stage($this->owner($row), $id, $locale, $staged);
        }
    }

    // Writes a translation straight away rather than staging it - what a seeder or a bulk pass does, having no form to wait for
    /** @param array<string, string|null> $values field => value */
    public function store(Book | BookCategory | Character | Contributor | Media | Serie | Strip $row, string $locale, array $values): void
    {
        $id = $row->getId();
        if (null !== $id) {
            $this->contentTranslator->store($this->owner($row), $id, $locale, $values);
        }
    }

    public function owner(Book | BookCategory | Character | Contributor | Media | Serie | Strip $row): string
    {
        return match (true) {
            $row instanceof Book => self::OWNER_BOOK,
            $row instanceof Serie => self::OWNER_SERIE,
            $row instanceof BookCategory => self::OWNER_CATEGORY,
            $row instanceof Strip => self::OWNER_STRIP,
            $row instanceof Character => self::OWNER_CHARACTER,
            $row instanceof Media => self::OWNER_MEDIA,
            default => self::OWNER_CONTRIBUTOR,
        };
    }

    /** @return list<string> */
    public function fields(Book | BookCategory | Character | Contributor | Media | Serie | Strip $row): array
    {
        return match (true) {
            $row instanceof Book => self::BOOK_FIELDS,
            $row instanceof Serie => self::SERIE_FIELDS,
            $row instanceof BookCategory => self::CATEGORY_FIELDS,
            $row instanceof Strip => self::STRIP_FIELDS,
            $row instanceof Character => self::CHARACTER_FIELDS,
            $row instanceof Media => self::MEDIA_FIELDS,
            default => self::CONTRIBUTOR_FIELDS,
        };
    }
}
