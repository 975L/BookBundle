---
name: c975l-book-lifecycle
description: "Use this skill when a row of a c975L BookBundle catalog is created, copied, replaced, hidden or moved between environments — publishing a new version of a book without losing its address, duplicating a book, a serie or a planche, the trash and the 410/301 answers that keep urls in order, what each repository read is allowed to answer, and the zip export/import that carries a whole catalog with its files. Triggers on: BookVersionPublisher, publishVersion, newerVersion, previousVersion, createPreviousVersion, moveEdition, moveMedias, BookDuplicator, duplicateBook, duplicateSerie, duplicateStrip, duplicateContributor, BookContributor, BookContributorType, BookTrashManager, moveToTrash, restore, deletePermanently, redirectSlugChange, TrashableInterface, TrashableTrait, TrashableCrudTrait, isDeleted, HideableTrait, isHidden, setHidden, hidden, GoneHttpException, 410, BookExportProvider, BookImportProvider, SerieExportProvider, SerieImportProvider, StripExportProvider, StripImportProvider, ContributorExportProvider, ContributorImportProvider, MediaArchiver, SerieResolver, ContributorResolver, BookCategory, BookCategoryResolver, BookCategoryExportProvider, BookCategoryImportProvider, duplicateCategory, findPublishedByCategory, findWithBooks, BookBackupPathProvider, BookSitemapProvider, BookLinkHealthCheckProvider, BookMediaMoveController, findAllPublished, findAllOnline, findPublishedByIds, findOneByNumber, publishedQueryBuilder, strip:import, strip:card, BookDemoFixtureProvider, BookSampleCatalog, DemoFixtureProviderInterface, getDemoFixtures, PlaceholderMediaRegistry, getImagesFor,  GalleryShowcaseProvider, BookReleaseAlert, BookReleaseAlertService, BookReleaseAlertRepository, BookReleaseAlertSend, BookMaintenanceTaskProvider, isToBePublished, findAllToBePublished, findReleased, purgeStale, isShownInCatalog, book_release_alert_unsubscribe, book_release_alert_unsubscribe_confirm, MAX_ATTEMPTS, attempts, BookEmailTemplateProvider."
---

# c975L BookBundle — a row's life

> A url is the thing worth protecting. A book keeps its address when its text is replaced, keeps answering 410 while it is in the trash, and answers 301 from the number and the slug it used to be reached by. Everything in this skill follows from that.

**Package:** `c975l/book-bundle` · **Bundle:** `c975L\BookBundle\` · **Translation domain:** `book`

**Key source paths** (relative to the package root):
`src/Service/BookVersionPublisher.php`, `src/Service/BookDuplicator.php`, `src/Service/BookTrashManager.php`, `src/Contract/TrashableInterface.php`, `src/Entity/Trait/TrashableTrait.php`, `src/Entity/Trait/HideableTrait.php`, `src/Controller/Management/Trait/TrashableCrudTrait.php`, `src/Controller/Management/BookCrudController.php`, `src/Controller/Management/BookMediaMoveController.php`, `src/Repository/BookRepository.php`, `src/Repository/SerieRepository.php`, `src/Repository/StripRepository.php`, `src/Repository/ContributorRepository.php`, `src/Repository/BookCategoryRepository.php`, `src/Management/BookExportProvider.php`, `src/Management/BookImportProvider.php`, `src/Management/ContributorExportProvider.php`, `src/Management/ContributorImportProvider.php`, `src/Management/ContributorResolver.php`, `src/Management/BookCategoryResolver.php`, `src/Management/BookCategoryExportProvider.php`, `src/Management/BookCategoryImportProvider.php`, `src/Management/MediaArchiver.php`, `src/Management/BookSitemapProvider.php`, `src/Management/BookLinkHealthCheckProvider.php`, `src/Command/StripImportCommand.php`, `src/Service/BookReleaseAlertService.php`, `src/Repository/BookReleaseAlertRepository.php`, `src/Command/BookReleaseAlertSend.php`, `src/Email/BookEmailTemplateProvider.php`, `src/Service/BookSampleCatalog.php`, `src/Service/BookDemoFixtureProvider.php`, `src/Service/GalleryShowcaseProvider.php`

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

```text
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

`BookDuplicator::duplicateBook()`, `duplicateSerie()`, `duplicateStrip()`, `duplicateContributor()` and
`duplicateCategory()` sit
behind the **Dupliquer** action of each index and edit screen. A book brings its editions, its files, its
platform links, its press, its videos, its marketing and its blocks; a serie brings its covers, its logos
and its blocks and **leaves its books and its planches with the original**; a planche brings its plates and
its blocks; a person brings their portrait, their backdrop and their blocks and **leaves what they signed
credited to the original** — a copy is a new person the editor then names, never a second author of the
same books.

A book **leaves its versions behind**: a duplicate is a new book the editor then qualifies, where chaining
one book behind another is the versions gesture's own doing. The title gets a `(copie)` suffix, the slug is
freed of collisions, `Book::$data` travels whole, and **every uploaded file is copied on disk** rather than
shared — deleting one of the two must never take the other's image with it. The service persists nothing,
so a command or a listener of the app can call it.

## Trash, 410 and 301

`Serie`, `Book`, `Strip`, `Contributor` and `BookCategory` all implement `TrashableInterface` through `TrashableTrait` —
one boolean column. Deleting from an index calls `BookTrashManager::moveToTrash()`: the row, its files and its blocks
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
| `BookRepository::findAll()` | trash excluded, rows set aside included — the health check, which walks the links of a row nobody is shown |
| `BookRepository::search()` | replaced versions included: a reader looking for an old text finds it |
| `ContributorRepository::findCredited()` | the people a shown book or serie credits — the index and the sitemap; someone whose only credits are replaced versions is off both |
| `BookCategoryRepository::findAll()` | the public listing of categories — trash and rows set aside both dropped |
| `BookCategoryRepository::findWithBooks()`, `SerieRepository::findWithBooks()` | the ones holding at least one book the catalog actually lists, the publication date included — an empty one, or one holding only titles still to come, would head a page with nothing under it |
| `BookRepository::findPublishedByCategory()` | the catalog cut down to one category, every rule of `findAllPublished()` kept, and nothing at all for a category set aside |
| `ContributorRepository::findOneByName()` | never someone in the trash: an import would credit them on a book, their page would answer 410, and the foreign key would refuse to remove them for good |

Every read above also drops what is set aside, `findAll()` excepted; `SerieRepository::findAll()` is the
public listing of series and does drop it, the back-office index building its own query.

A trashed row also leaves the search components, the sitemap and the serie autocomplete of the forms.

## Set aside, which is not the trash

`Serie`, `Book`, `Strip`, `Contributor` and `BookCategory` carry a second boolean, through `HideableTrait` — **`hidden`**,
the switch each of the five CRUD screens offers on its index as well as on its form. A row set aside is off
the site for exactly as long as the box is ticked: no listing shows it, its page answers **404**, and the sitemap stops
declaring it at its next run (`c975l:sitemaps:create`). Nothing of it is touched, so it comes back whole
with one click.

| | Means | Answers | Set from |
| --- | --- | --- | --- |
| `isDeleted` | on its way out, restorable | **410**, a crawler drops the page | the index' delete action |
| `hidden` | simply not shown, nothing taken away | **404** | the switch on the row |

**A serie is refused the switch while it still holds a book or a planche that is shown** (`holdsVisibleContent()`),
the same guard `deleteEntity()` carries for the trash — what it holds would name a serie answering 404. **A
person is refused both while a book or a serie still credits them** — as its author, its illustrator, or under
a `BookContributor` row naming any other part — for the same reason and by the same
method; the index switch is refused in `ajaxEdit()`, which answers an error so the switch goes back rather
than staying on a row nothing was stored for. A person set aside also leaves the author and illustrator
autocompletion of a book — crediting them would point at a page answering 404.

The archive carries it: `hidden` travels through the four export/import providers, and an archive written
before the flag existed reads as shown.

## Telling readers a book is out

A book with no date, or with a date still to come, is **à paraître** (`Book::isToBePublished()`, the same
reading `findAllToBePublished()` gives the listing) and carries a link to a page where a visitor leaves an
address — a page of its own, the sheet's html being cached per fragment where a form needs a session.

`BookReleaseAlert` holds one row per waiting visitor: the book, the address, the locale it was taken in, a
token and the failed sends counted on it (`attempts`), and **nothing else**. Subscribing is acknowledged at
once by `book_release_alert_confirmation`, which carries the unsubscribe link — the only way out offered
before the parution, and what somebody whose address was typed by a third party leaves from. `BookReleaseAlertSend`,
scheduled nightly by `BookMaintenanceTaskProvider`, sends `book_released`
to everyone whose book has come out and **deletes the row with it**: a book is published once, so the
parution e-mail carries no unsubscribe link and the address is not kept past the purpose it was given for.
The same run drops the lists of books announced and never published (`purgeStale()`, 24 months).

**The unsubscribe link opens a page, it does not unsubscribe.** `book_release_alert_unsubscribe` answers a
`GET` with a confirmation page carrying a button, and `book_release_alert_unsubscribe_confirm` — the same
url in `POST`, behind a csrf token bound to the row's own token — is what drops the row. Deleting on the
`GET` had the mail gateways that walk every address of a message before it is read (Outlook's Safe Links,
an antivirus) unsubscribe the reader minutes after the acknowledgement reached them.

Which books count is `findReleased()`, whose conditions are `publishedQueryBuilder()`'s own, asked in the
database: a book set aside, one of a serie set aside, one in the trash or one replaced by a newer version
is not published, and nobody is told about it. `subscribe()` reads those same conditions through
`Book::isShownInCatalog()` and refuses the address rather than acknowledging a message that would never be
sent. An address the mailer keeps refusing is counted and let go after `MAX_ATTEMPTS` (3) nights, the queue
being ordered by that count first — a handful of dead addresses used to fill every batch from the head of
the list. A book deleted takes its list with it (`onDelete: CASCADE`).

## Moving files and moving catalogs

`BookMediaMoveController` moves a file from one collection of a book to another — what is leafed through
before buying, what runs through the slider — without uploading it again. **An edition is no target**: it
carries only what the book comes out under.

The ten export/import providers carry a serie, a book, a planche, a person or a category whole — files
included — as a zip, through ConfigBundle's **Export sync (everything)** shortcut, each index's **Export
selection** batch action and the **Import content** screen. `MediaArchiver` puts the files in and takes them
back out; `SerieResolver` matches a serie and `BookCategoryResolver` the categories a book names, both
creating on the fly what this environment doesn't hold yet. **Everything is matched by slug, never by id**,
and an import never deletes what the archive does not name — a book's `categories` are the one list replaced
whole, an archive saying which categories the book carries and a category taken off it there coming off it
here too.

**A person is the one exception, matched by name** (`ContributorResolver`): a book's archive still carries
`author`, `authorWebsite`, `illustrator` and `illustratorWebsite` as the plain strings the four columns held
before v2.5, and its `contributors` rows name theirs the same way, so the name is the only key an older
archive has to offer. The resolver creates the row when
this environment holds none, and `ContributorImportProvider` fills in later whatever such a row is missing
— the two meet on the same slug rather than doubling the person. The name is only ever fallen back on where
the row found is that **shell** — nothing but a name and a slug: somebody the site has actually written up is
a namesake, and gets a row of their own rather than having their page overwritten. Platforms are overwritten on their kind, a book having one address
per platform.

Two console commands stand beside them, both for a bulk of planches: `strip:import`, which reads an older
site's table and directory into `book_strip`/`book_media`, and `strip:card`, which hands a directory of square
captures (see `c975l-book-display-pages`) to the media each planche already carries — through **Vich**, which
names the new file and deletes the one it replaces. Vich names with a fresh `uniqid`, so the same run against
two databases yields two different names: run it where the catalog is the source of truth, and bring the
result back.

## A catalog to browse before there is one

`BookDemoFixtureProvider` (UiBundle's `DemoFixtureProviderInterface`) seeds a demo site with the made-up
catalog `BookSampleCatalog` holds — two series of two books, three out and one still to come. Every title
and summary is a key of the `book` domain, so a site seeded in Spanish reads as a Spanish catalog; the two
credited names are literal, and so is the age each book is read at, digits carrying no language.

The series are yielded first and the books after: `Serie::$books` is the inverse side and nothing cascades
off it, so each book is recorded on its own. Pictures come from `PlaceholderMediaRegistry`, keyed
**`book/<slug>`** and **`serie/<slug>`** — a book reads its cover, its fourth cover and its backdrop off
them, a serie its cover, its emblem and its backdrop, in that order. Failing a declared key, one of the
generic pool is read off the slug. A site declaring none still gets a catalog: the card falls back on the
bundle's `no-cover.webp`.

`GalleryShowcaseProvider` reads the very same `BookSampleCatalog` for the block showcase, where it builds
entities it never writes. **One dataset, two readings** — enrich it in the catalog, not in either consumer.
Its cards carry a cover too, read off the same declared keys, so a demo site and the showcase show the same
book. Failing one, it deals the generic pool **by rank** where the fixtures read it off the slug: a rail is
seen side by side, and two cards sharing a photograph read as a bug. A cover is posed with `addCover()`, not
`setName()` alone — `book_cover()` reads the kind before falling back on the first image.

## Do not

- **Do not add an edition to mean a new version of the text** — an edition is a format.
- **Do not put a file, a platform or a date on `BookEdition`** — they belong to the book.
- **Do not reattach a row taken out of a collection** — `orphanRemoval` has already scheduled its deletion; write the owning side.
- **Do not move an edition or a media onto a book that is not flushed yet.**
- **Do not iterate a collection while moving its rows out of it** — read it into an array first.
- **Do not chain a version onto a book that already carries a `newerVersion`** — chain onto that one.
- **Do not change a book's slug when its text is replaced** — the address must lead to the current version.
- **Do not answer 404 for a trashed row** — 410 is what tells a crawler to drop it.
- **Do not answer 410 for a row set aside** — nothing was taken away, 404 is what it is worth.
- **Do not hide a serie that still holds something shown** — set what it holds aside first.
- **Do not give a category a parent** — it is flat on purpose; the trade's codes go in `BookCategory::getCode()`, the tree does not follow them.
- **Do not trash or hide a person a book or a serie still credits** — credit somebody else first.
- **Do not credit the same person twice under the same part of one book** — the `book_contributor_role` constraint refuses it, and `Book::$contributors` raises `label.contributor_role_duplicate` before the flush rather than letting the database answer with a 500.
- **Do not write a public query without the trash and draft filters** — pick a repository read.
- **Do not pose a showcase cover with `setName()` alone** — `addCover()` is what files it as a cover.
- **Do not share an uploaded file between an original and its copy.**
- **Do not match an import by id** — slugs are what survive an environment change.
- **Do not write a made-up book into a consumer** — `BookSampleCatalog` holds the dataset both of them read.
- **Do not move a catalog with the SQL/CSV/JSON dumps** — they carry one table at a time; the zip carries a row whole with its files.
