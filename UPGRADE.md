# UPGRADE Guide

This document describes breaking changes and how to upgrade between major versions.

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
