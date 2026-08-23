---
name: c975l-book-lifecycle
description: "Use this skill when a row of a c975L BookBundle catalog is created, copied, replaced, hidden or moved between environments — publishing a new version of a book without losing its address, duplicating a book, a serie or a planche, the trash and the 410/301 answers that keep urls in order, what each repository read is allowed to answer, and the zip export/import that carries a whole catalog with its files. Triggers on: BookVersionPublisher, publishVersion, newerVersion, previousVersion, createPreviousVersion, moveEdition, moveMedias, BookDuplicator, duplicateBook, duplicateSerie, duplicateStrip, BookTrashManager, moveToTrash, restore, deletePermanently, redirectSlugChange, TrashableInterface, TrashableTrait, TrashableCrudTrait, isDeleted, GoneHttpException, 410, BookExportProvider, BookImportProvider, SerieExportProvider, SerieImportProvider, StripExportProvider, StripImportProvider, MediaArchiver, SerieResolver, BookBackupPathProvider, BookSitemapProvider, BookLinkHealthCheckProvider, BookMediaMoveController, findAllPublished, findAllOnline, findPublishedByIds, findOneByNumber, publishedQueryBuilder, strip:import."
---

# c975L BookBundle — a row's life

> A url is the thing worth protecting. A book keeps its address when its text is replaced, keeps answering 410 while it is in the trash, and answers 301 from the number and the slug it used to be reached by. Everything in this skill follows from that.

**Package:** `c975l/book-bundle` · **Bundle:** `c975L\BookBundle\` · **Translation domain:** `book`

**Key source paths** (relative to the package root):
`src/Service/BookVersionPublisher.php`, `src/Service/BookDuplicator.php`, `src/Service/BookTrashManager.php`, `src/Contract/TrashableInterface.php`, `src/Entity/Trait/TrashableTrait.php`, `src/Controller/Management/Trait/TrashableCrudTrait.php`, `src/Controller/Management/BookCrudController.php`, `src/Controller/Management/BookMediaMoveController.php`, `src/Repository/BookRepository.php`, `src/Repository/SerieRepository.php`, `src/Repository/StripRepository.php`, `src/Management/BookExportProvider.php`, `src/Management/BookImportProvider.php`, `src/Management/MediaArchiver.php`, `src/Management/BookSitemapProvider.php`, `src/Management/BookLinkHealthCheckProvider.php`, `src/Command/StripImportCommand.php`

**Related skills:** `c975l-book-display-pages` and `c975l-book-customization` in this same bundle, and `c975l-config`, `c975l-media` in the core beside it.

## An edition is not a version

| | Is | Carries |
| --- | --- | --- |
| an **edition** (`BookEdition`) | a format — paper, digital, audio | its ISBN, its size, its pagination |
| a **version** (`Book::$newerVersion`) | another text — rewritten, revised, newly illustrated | its own book row, its own page, its own ISBNs |

Every format exists for both texts, which is why a new version is a book of its own and never one more
edition. **An edition holds no file, no platform and no date**: the files and the platforms belong to the
book, under the gesture they serve, and the date is the book's — the only one there is.

## Publishing a new version

The **Publier une nouvelle version** action of a book's screen (`site-role-editor`) calls
`BookVersionPublisher::createPreviousVersion()`. The book **keeps its address and its identity** — its id,
its slug, its readers' ratings, its blocks, its translations — and a twin is born taking what came out so
far, its editions among them with `moveEdition()`, and the pages of that text with `moveMedias()`.

```
book (kept, same url)  ← newerVersion —  twin (what came out so far)  ← newerVersion —  the one before
```

The twin only leaves the catalog: it keeps its page, its ISBN and its place in the sitemap, and is reached
from the search or from the book replacing it. **A book already replaced is not replaced again** — the
version replacing it comes in versions in its turn, so a third version chains onto the second rather than
beside it, the link being one-to-one at both ends.

Three rules `BookVersionPublisher` exists to enforce, to be honoured by anything else moving a row:

- the old link is **freed and flushed first** — `newerVersion` is unique in database, and the twin cannot
  name the book while the old row still does;
- the target book is **saved before any move** — an edition can only name a book the database knows;
- editions and medias are **read into an array before the loop moves them** — moving detaches each from
  the book, and the collection would change under the iteration. `moveEdition()` and `moveMedias()` write
  on the owning side only, never through `removeEdition()`: the collections are `orphanRemoval: true`, and
  taking a row out schedules it for deletion whatever it is reattached to afterwards.

## Duplicating

`BookDuplicator::duplicateBook()`, `duplicateSerie()` and `duplicateStrip()` sit behind the **Dupliquer**
action of each index and edit screen. A book brings its editions, its files, its platform links, its press,
its videos, its marketing and its blocks; a serie brings its covers, its logos and its blocks and **leaves
its books and its planches with the original**; a planche brings its plates and its blocks.

A book **leaves its versions behind**: a duplicate is a new book the editor then qualifies, where chaining
one book behind another is the versions gesture's own doing. The title gets a `(copie)` suffix, the slug is
freed of collisions, `Book::$data` travels whole, and **every uploaded file is copied on disk** rather than
shared — deleting one of the two must never take the other's image with it. The service persists nothing,
so a command or a listener of the app can call it.

## Trash, 410 and 301

`Serie`, `Book` and `Strip` all implement `TrashableInterface` through `TrashableTrait` — one boolean
column. Deleting from an index calls `BookTrashManager::moveToTrash()`: the row, its files and its blocks
stay exactly as they were, and its public page answers **410 Gone** rather than the 404 an url that never
existed gets, which is what a search engine drops an indexed page on. `restore()` and `deletePermanently()`
are `site-role-admin`, the rest of the screens `site-role-editor`; `TrashableCrudTrait` lays the toggle and
the two actions out.

| Event | Answer left behind |
| --- | --- |
| moved to the trash | 410, for as long as it can be restored |
| removed for good | a `gone` Redirect answering 410 there permanently |
| renamed | a 301 from the old path (`redirectSlugChange()`) |

`deletePermanently()` also drops what hangs off `owner type + id` and is cascaded by nothing: a row's
ratings, and a book's reviews.

**Every public read honours the flag.** A new lookup picks from this table rather than writing its own
`where`:

| Read | Answers |
| --- | --- |
| `BookRepository::findAllPublished()`, `countPublished()`, `findPublishedByIds()` | published, not trashed, not replaced by a newer version |
| `BookRepository::findOneByNumber()`, `StripRepository::findOneByNumber()` | what the catalog reads today — never a draft, never the trash |
| `BookRepository::findAllOnline()` | every page that still answers, replaced versions included — the sitemap |
| `BookRepository::findAll()` | trash excluded — the health check |
| `BookRepository::search()` | replaced versions included: a reader looking for an old text finds it |

A trashed row also leaves the search components, the sitemap and the serie autocomplete of the forms.

## Moving files and moving catalogs

`BookMediaMoveController` moves a file from one collection of a book to another — what is leafed through
before buying, what runs through the slider — without uploading it again. **An edition is no target**: it
carries only what the book comes out under.

The six export/import providers carry a serie, a book or a planche whole — files included — as a zip,
through ConfigBundle's **Export sync (everything)** shortcut, each index's **Export selection** batch
action and the **Import content** screen. `MediaArchiver` puts the files in and takes them back out;
`SerieResolver` matches a serie. **Everything is matched by slug, never by id**, and an import never
deletes what the archive does not name. Platforms are overwritten on their kind, a book having one address
per platform.

`strip:import` is the one console command, for a bulk of planches.

## Do not

- **Do not add an edition to mean a new version of the text** — an edition is a format.
- **Do not put a file, a platform or a date on `BookEdition`** — they belong to the book.
- **Do not reattach a row taken out of a collection** — `orphanRemoval` has already scheduled its deletion; write the owning side.
- **Do not move an edition or a media onto a book that is not flushed yet.**
- **Do not iterate a collection while moving its rows out of it** — read it into an array first.
- **Do not chain a version onto a book that already carries a `newerVersion`** — chain onto that one.
- **Do not change a book's slug when its text is replaced** — the address must lead to the current version.
- **Do not answer 404 for a trashed row** — 410 is what tells a crawler to drop it.
- **Do not write a public query without the trash and draft filters** — pick a repository read.
- **Do not share an uploaded file between an original and its copy.**
- **Do not match an import by id** — slugs are what survive an environment change.
- **Do not move a catalog with the SQL/CSV/JSON dumps** — they carry one table at a time; the zip carries a row whole with its files.
