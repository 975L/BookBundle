# UPGRADE Guide

This document describes breaking changes and how to upgrade between major versions.

## v2.5

**An author is now a row, not a string.** `Book` and `Serie` each lose `author`, `author_website`,
`illustrator` and `illustrator_website`, and gain `author_id` and `illustrator_id` pointing at the new
`Contributor` (table `book_contributor`). One entity holds both roles — writing and illustrating are two
roles, not two natures, and the same person often holds both — so a person exists once, with one page and
one slug, however many hats they wear.

**The migration `doctrine:migrations:diff` writes is not usable as it comes out.** It puts the `ADD` of the
two id columns and the `DROP` of the four string columns in the same `ALTER TABLE`, which throws the names
away before anything can read them. Split it: the `ADD`s first, then the steps below, then the
`DROP`s. Check as well that the diff has not turned an `author` dropped and an `author_id` added into a
`CHANGE author author_id` — it pairs a dropped and an added column of a compatible type and calls it a
rename, which would fill the foreign key with names.

```sql
-- 1. One row per distinct name, across the four columns of the two tables
INSERT INTO book_contributor (name, slug, website, position, creation, modification, hidden, is_deleted)
SELECT
    name,
    LOWER(REPLACE(REPLACE(REPLACE(name, ' ', '-'), '.', ''), '''', '-')),
    MAX(website),
    0, NOW(), NOW(), 0, 0
FROM (
    SELECT author AS name, author_website AS website FROM book_book WHERE NULLIF(author, '') IS NOT NULL
    UNION ALL SELECT illustrator, illustrator_website FROM book_book WHERE NULLIF(illustrator, '') IS NOT NULL
    UNION ALL SELECT author, author_website FROM book_serie WHERE NULLIF(author, '') IS NOT NULL
    UNION ALL SELECT illustrator, illustrator_website FROM book_serie WHERE NULLIF(illustrator, '') IS NOT NULL
) AS people
GROUP BY name;

-- 2. Each book and each serie points at the row its name stands for
UPDATE book_book b JOIN book_contributor c ON c.name = b.author SET b.author_id = c.id;
UPDATE book_book b JOIN book_contributor c ON c.name = b.illustrator SET b.illustrator_id = c.id;
UPDATE book_serie s JOIN book_contributor c ON c.name = s.author SET s.author_id = c.id;
UPDATE book_serie s JOIN book_contributor c ON c.name = s.illustrator SET s.illustrator_id = c.id;

-- 3. Nothing left unmatched, which would be a name lost on the way - the four together must answer 0
SELECT
    (SELECT COUNT(*) FROM book_book WHERE NULLIF(author, '') IS NOT NULL AND author_id IS NULL) AS book_author,
    (SELECT COUNT(*) FROM book_book WHERE NULLIF(illustrator, '') IS NOT NULL AND illustrator_id IS NULL) AS book_illustrator,
    (SELECT COUNT(*) FROM book_serie WHERE NULLIF(author, '') IS NOT NULL AND author_id IS NULL) AS serie_author,
    (SELECT COUNT(*) FROM book_serie WHERE NULLIF(illustrator, '') IS NOT NULL AND illustrator_id IS NULL) AS serie_illustrator;

-- 4. Two distinct names can have given the same slug ("Jean-Luc Picard" and "Jean Luc Picard"): rename before the unique index is laid
SELECT slug, COUNT(*) AS nb, GROUP_CONCAT(name) FROM book_contributor GROUP BY slug HAVING nb > 1;
```

**`slug` carries a unique index**, and that is the third thing to take out of the diff: `book_contributor`
being a new table, the index is written inside its `CREATE TABLE`, where it would make step 1 fail as a
whole rather than let query 4 be read — SQL cannot tell two spellings of one name from two people, so it
groups by name and lets both rows in. Drop the `UNIQUE` off the column in the `CREATE TABLE`, run steps 1
to 4, rename in the back office every slug query 4 lists — a renamed slug leaves a 301 behind, so nothing
breaks — then lay the index for good:

```sql
CREATE UNIQUE INDEX UNIQ_book_contributor_slug ON book_contributor (slug);
```

**The slugs that query builds are provisional**: SQL cannot strip accents, so "Ludmila Brazović" comes out
as `ludmila-brazović`. Look the list over in the back office after the migration and rewrite the ones that
need it — a renamed slug leaves a 301 behind, so nothing breaks.

**Two new ConfigBundle entries**, `book-route-contributors` and `book-route-contributor`, seeded at
"auteurs" and "auteur". Left empty, neither page is served — which is what a site whose catalog credits
nobody wants. Run `c975l:config:import` (or whatever your app calls it) so they land in the dashboard.

**What changes in your own code**, if you wrote any against these:

| Was | Is now |
| --- | --- |
| `$book->getAuthor()` → `?string` | → `?Contributor` |
| `$book->getEffectiveAuthor()` → `?string` | → `?Contributor` |
| `$book->getAuthorWebsite()` | gone — `$book->getAuthor()?->getWebsite()` |
| `$book->getEffectiveAuthorWebsite()` | kept, reads the address off the person |
| `$serie->setAuthor('Tim Loval')` | `$serie->setAuthor($contributor)` |

**A template printing `{{ book.author }}` keeps working**: `Contributor::__toString()` returns the name.
One printing `{{ book.authorWebsite }}` does not — use `{{ book.effectiveAuthorWebsite }}`, which stayed.

**Your content archives read on either version.** The book and serie exports still carry `author`,
`authorWebsite`, `illustrator` and `illustratorWebsite` as plain strings; on the way in, `ContributorResolver`
matches each name against the people already recorded and creates the row when this environment holds none.
So an archive taken before this version imports unchanged, and one taken after imports into a site still on
v2.4.

## v2.4.0

**A new column on three tables: `hidden`** (boolean, default `false`), on `book_serie`, `book_book` and
`book_strip`. It carries a row set aside: kept whole in the back office, off every public listing and every search, its page answering 404,
and out of `sitemap-book.xml` at the next `c975l:sitemaps:create`. **Generate and run the migration**;
nothing else to do, every existing row landing shown.

It is not the trash and does not replace it: `isDeleted` still means "deleted, restorable" and still
answers 410. See the README's [Setting a row aside](README.md#setting-a-row-aside).

## v2.2.0

**KnpPaginatorBundle leaves the bundle's dependencies.** The catalog, the series and the planches grow as the
visitor scrolls rather than turning pages, and `BookService`, `SerieService` and `StripService` return CoreBundle's
`c975L\UiBundle\Model\Pagination` where they returned Knp's `PaginationInterface`. The two answer the same
figures - `getCurrentPageNumber()`, `getPageCount()`, `getTotalItemCount()`, `getItemNumberPerPage()`, `route`,
`query()` - and are countable and iterable alike, so a template reading one of those reads the other unchanged.
**An app implementing `BookServiceInterface`, `SerieServiceInterface` or `StripServiceInterface` itself, or
type-hinting `PaginationInterface` on what they return, has that type to change.** See CoreBundle's UPGRADE.md for
removing the package from the app.

**A template overriding `book/index.html.twig`, `serie/index.html.twig` or `strip/index.html.twig` keeps its page
links** and goes on working, `<twig:c975LUi:Pagination:Pagination>` aside, which is removed: those overrides render
the links themselves or take the bundle's own templates back.

## v2.1.0

**A book no longer holds a trailer or a filmed episode.** The `trailer` and `podcast` file kinds leave
`BookMediaKind`, their two collections leave the back office (`BookCrudController`), and the "Watch" and "Listen"
cards no longer print them. Nothing in the schema changes — a kind is a plain string on `book_media` — so
`doctrine:migrations:diff` has nothing to say here. What changes is that the rows carrying those two kinds become
unreachable: no field edits them any more, and no page renders them.

**If your catalog has uploaded any**, decide what becomes of them before upgrading. They are inert either way, but
they go on taking their room on the disk. To see what you hold:

```sql
SELECT kind, COUNT(*) FROM book_media WHERE kind IN ('trailer', 'podcast') GROUP BY kind;
```

To be rid of them, move the files out of `public/medias/book/books/` first — the rows are what names them, and a
deleted row leaves no trace of which file belonged to it — then:

```sql
DELETE FROM book_media WHERE kind IN ('trailer', 'podcast');
```

**If you have overridden `Book/Podcasts.html.twig` or `Book/Videos.html.twig`**, both lost the block printing that
video. The rest of each card is untouched: the recording, the download buttons and the podcast apps under "Listen",
the book's own videos and the platforms under "Watch".

**If you read `BookSectionsExtension::CARD_KINDS` or the `book_card_kinds()` Twig function**, both are gone. They
named the two kinds a card fetched from whichever version carried them, and there is no longer anything to fetch.
`book_medias_of_kind()` stays, for whatever kind your own site names.

**If you declare your own file kinds** through `BookCustomizationProviderInterface::getMediaKinds()`, nothing
changes — and `trailer` is now a word the bundle leaves free, so a site still wanting one declares it and prints it
in a fragment of its own (see `getSections()`).

**Four translation keys leave the `book` domain**: `label.media_trailer`, `label.media_podcast`,
`label.trailer_media-help` and `label.podcast_media-help`. `label.videos-help` and `label.watch-intro` no longer
mention the trailer either. Override them in your app only if you had overridden them before.
