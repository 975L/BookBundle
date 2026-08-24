# BookBundle

Symfony bundle providing a publisher's catalog of books and series on the c975L core — media, video, press and marketing collections, EasyAdmin CRUD, paginated public routes, multilingual editions and its own sitemap.

[![GitHub](https://img.shields.io/github/license/975L/BookBundle)](https://github.com/975L/BookBundle/blob/master/LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/c975l/book-bundle)](https://packagist.org/packages/c975l/book-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/c975l/book-bundle)](https://packagist.org/packages/c975l/book-bundle)
[![Codacy Grade](https://app.codacy.com/project/badge/Grade/a9a849a68d024496875550ae58ab4c2d)](https://app.codacy.com/gh/975L/BookBundle/dashboard)

## Why BookBundle

![BookBundle](.github/images/BookBundle.svg)

Add BookBundle on top of the [c975L core](https://github.com/975L/CoreBundle) to get a publishing catalog — no dependency on SiteBundle, ShopBundle, SocialBundle or any other satellite bundle, so it drops into any c975L site that needs one. Media collections, admin CRUD and export reuse the core's shared mechanisms rather than duplicating them.

---

> **TL;DR** — A publishing catalog of books and series, each book carrying media, video, press and marketing sub-collections, with EasyAdmin CRUD, paginated public routes and a dedicated sitemap. Depends only on the c975L core, so it drops into any c975L site.

## Contents

- **Setup** — [requirements](#requirements) · [installation](#installation) · [configuration](#load-the-configuration) · [routes](#enable-routes) · [assets](#install-assets)
- **Using it** — [public routes](#routes) · [editions](#editions) · [duplicating](#duplicating-a-book-a-serie-or-a-strip) · [trash, redirects and 410](#trash-redirects-and-410) · [customizing the catalog](#customizing-the-catalog) · [links](#links) · [ISBN filter](#isbn-filter) · [blocks](#blocks) · [structured data](#structured-data) · [sitemap](#sitemap) · [health check](#health-check) · [export / import](#export--import-the-catalog) · [backup](#backup)

## Features

- Book and series catalog with paginated list and detail views
- Each book supports media, video, press, and marketing sub-collections with drag-and-drop ordering
- Series group books with sorted ordering
- Multilingual: books can reference translations across languages
- Each published form of a book — paperback, ebook, audiobook, illustrated edition — as a row carrying its own ISBN, its size and its page count
- Where a book is read, listened to or watched, as rows rather than columns — adding a platform is an enum case, not a migration
- What a single site adds to a book — its own fields, its own media and edition vocabulary — declared rather than coded into an overridden CRUD
- Admin CRUD via EasyAdmin for books and series
- A book, a serie or a strip duplicated in one click, with its files, its editions, its platforms and its blocks
- A new version of a book published in one click: the book keeps its address and its readers, a twin carries what came out so far
- Reader reviews on a book's page, behind UiBundle's `ui-enable-reviews` setting
- Deletion goes through a trash, and the urls that leave the site answer 410 rather than 404 — a renamed one 301s to its new address
- Live component search for books
- Books, series and strips are composable in blocks, with the kinds of UiBundle
- schema.org `Book`, `BookSeries` and `ComicStory` data published as JSON-LD
- Sitemap generation, feeding the site's `llms.txt`
- The platform addresses a catalog sends its readers to, checked weekly on the Health check dashboard
- Public url prefixes settable from the back office, and settable to nothing — a site reading its books under its own routes serves no page of the bundle's
- Guided projects and linkable routes contributed to the management interface
- The whole catalog exported in one archive and re-imported elsewhere, files included, through ConfigBundle's sync screens
- Twig `isbn` filter for formatting ISBN numbers
- Three skills written for the coding agents of the sites installing this bundle, shipped in the package and read straight from `vendor/` (see [AI agent skills](#ai-agent-skills))

---

## Requirements

- PHP >= 8.4
- [c975L/CoreBundle](https://github.com/975L/CoreBundle)
- Doctrine ORM
- EasyAdmin
- KNP Paginator Bundle
- symfony/ux-live-component
- symfony/ux-twig-component
- VichUploader Bundle

---

## Installation

### Download

```bash
composer require c975l/book-bundle
```

### Run migrations

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

[`UPGRADE.md`](UPGRADE.md) names the breaking changes of each version and what a consuming app does about
them — the notes below are the ones asking for SQL of their own.

Upgrading from a version where a serie's covers were stored with no kind, tag them so they keep their `cover` kind:

```sql
UPDATE book_serie_media SET kind = 'cover' WHERE kind IS NULL;
```

Upgrading from a version where a file or a platform named the edition it was published in, **the
`edition_id` columns are dropped**: a book's files and its platforms belong to the book, and an edition
says only what the book comes out under. The rows themselves stay where they are, and the migration diff
carries the two drops:

```sql
ALTER TABLE book_media DROP FOREIGN KEY FK_book_media_edition, DROP COLUMN edition_id;
ALTER TABLE book_link DROP FOREIGN KEY FK_book_link_edition, DROP COLUMN edition_id;
```

A row with no kind is read as nothing at all, whatever it holds. Unlike a serie, where such a row could
only be a cover, a book's may just as well be pages, a recording or a flipbook, so tagging them is a
site-by-site decision rather than one statement run over the whole table.

### Load the configuration

The bundle ships the url prefixes of its public pages as ConfigBundle entries (group **Catalogue**):

```bash
php bin/console c975l:config:load-all
```

### Enable routes

Add the bundle routes to `config/routes.yaml`:

```yaml
c975_l_book:
    resource: "@c975LBookBundle/src/Controller/"
    type: attribute
    prefix: /
```

### Install assets

```bash
php bin/console assets:install --symlink
```

The `book_reader` kind ships a Stimulus controller, with nothing to register by hand: `Service\ScriptProvider`
has the front layout load `controllers.js` wherever `bundle_scripts()` is rendered, and its `importmap.php` entry is
written by `Management\ImportmapProvider` the first time you `composer update` after installing the bundle -
`php bin/console c975l:config:check-importmap` reports it if it is missing.

The bundle's own stylesheet (`public/css/styles.min.css`) is contributed to UiBundle's registry by
`Service\StylesheetProvider`, so nothing has to import it: `assets:install` is all it needs. It carries
shapes only — a book page is laid on the site's own ground and ink, read through UiBundle's
`--background`/`--text`/`--primary`, which the **theme** config group already feeds. What a design retunes
ships commented out in `scaffold/assets/styles/themes/book.css`, copied into the app once by
`c975l:scaffold:install` and owned by the app from then on.

---

## Usage

### Routes

| Route | URL | Setting | Description |
| --- | --- | --- | --- |
| `book_index` | `/livres` | `book-route-books` | Paginated book list |
| `book_display` | `/livre/{slug}` | `book-route-book` | Book detail page |
| `serie_index` | `/series` | `book-route-series` | Paginated list of the book series |
| `serie_display` | `/series/{slug}` | `book-route-series` | Book series detail page, listing what it holds |
| `strip_index` | `/strips` | `book-route-strips` | Paginated list of the strip series |
| `strip_serie_display` | `/strips/{slug}` | `book-route-strips` | Strip series detail page, listing the planches it tells |
| `strip_display` | `/strip/{slug}` | `book-route-strip` | Strip detail page |
| `book_shortcut` | `/b{number}` | `book-route-book-shortcut` | Short link to a book, 301 |
| `strip_shortcut` | `/s{number}` | `book-route-strip-shortcut` | Short link to a strip, 301 |

The two indexes share no serie: each one lists the series of its own kind, `Serie::kind` saying which
(`Enum\SerieKind`), and a serie that declares none is filed by what it holds. A planche is listed by the
serie telling it and nowhere else — the serie's page lists them whole, and filters them on who speaks
through `?character=maman` — so the same set is never served at two addresses.

A serie is read *below* the index listing it, sharing its very segment: `/series/my-serie` for the ones
telling books, `/strips/my-serie` for the ones telling planches. Both are the same controller action
under two route names, `BookPublicUrlResolver::serieRoute()` saying which one a serie wears — and asked
under the other segment, a serie answers `301` towards its own. In Twig, `serie_path(serie)` and
`serie_url(serie)` generate them, so a template never has to tell the two apart.

A book and a strip also answer to their **number**, wherever their slug is expected: `/livre/3` and
`/livre/003-le-tracteur` both 301 to `/livre/le-tracteur`. The slug is tried first, so a book actually
named `003-something` is served rather than redirected — the number is a fallback, never a route that
preempts. That is what carries over the numbered urls a site served before it read its catalog by slug.

The **short link** is the same thing said shorter, for a social network where an address is read out and
typed: one letter and the number, `/b3` for a book, `/s3` for a strip. The letter is the site's own —
`h` where the books are stories, `r` where the strips are replies — and left empty, no such url is
served. A number always leads to the row the catalog still lists, never to a version a newer one
replaced (`BookRepository::findOneByNumber()`).

The first segment of each url is a setting, group **Catalogue** in **Configuration**, so a site serves the
catalog in its own language — `books`, `libros`, `planches`. A route path is compiled into the router's
cache, so the prefix can't *be* the path: each route is declared as `/{book_prefix}/…`, carrying it as a
route parameter, and its condition asks `Routing\BookRoutePrefix` whether the segment it was handed is the
configured one. Any other value simply doesn't match and the router carries on with the rest of the site's
routes — without that check, `/{book_prefix}/{slug}` would swallow every two-segment url. Generating a url
needs nothing of this: `BookRoutePrefixListener` puts the prefixes in the router's request context, so
`path('book_display', {slug: …})` keeps taking the slug alone.

**Left empty, a setting takes its pages off the site altogether** — unlike GalleryBundle's own prefix, which
falls back to a default. That is what a site whose books are read under its own routes sets: papa-calin.com
reads them as `/histoire/{numéro}-{slug}`, from its own controller, and would otherwise serve every story at
two addresses competing for the same search result. The sitemap and the menu targets follow: a family whose
prefix is empty declares nothing (see `BookSitemapProvider`, `Management\LinkableRouteProvider`).

A site that turns a family off must not use that family's public components either (`Book:Book`,
`Serie:Serie`, `Strip:Card`…) nor the blocks built on them: they link to pages that are no longer served.

### Display pages

The three detail pages — a book, a serie, a planche — are laid out mobile first: one column, in source
order, and the wider steps are what a screen adds rather than what a phone has to undo. Each opens on a
hero carrying the page's only `h1`, which is why the templates leave the layout's own `title` block empty.

The catalog page lists what the site says it lists: `BookCatalogProviderInterface` (tag `book.catalog_provider`)
answers with the books and with the word the site gives them, and null hands the page back to the bundle's own
published books. It is deliberately not part of `BookCustomizationProviderInterface`, which names a catalog's
vocabulary — which rows a page lists, and in what order, is a query and not a word. A site reading a story as
out on a date of its own answers here rather than overriding the controller.

The summary is not one of those sections: it is the sentence a book opens on, printed by `Book:Resume` under
the hero and outside the grid, with no title and no anchor — a summary is not a destination anyone jumps to.

A book's and a serie's sections are named once, by `book_sections(book)` and `serie_sections(serie)`
(`Twig\BookSectionsExtension`), and the page reads that list twice: once to build its summary of anchors
(`<twig:c975LUi:Text:Toc>`, UiBundle), once to decide what to render. A section is therefore never offered
in the summary without being on the page, nor rendered without an anchor pointing at it — `extracts`,
`podcasts`, `apercu`, `crowdfunding`, `shops`, `presse`, `marketing`, `informations` for a book, `books`
and `strips` for a serie. Each section wears UiBundle's `toc-target`, which leaves the room the
resting summary bar covers, so a jump doesn't land a title under it.

The summary is a bar of chips under the header on a phone and a column beside the sections from `1200px`
on; the labels come from the `book` translation domain, in the book's own language rather than the
visitor's. A planche gets the same skeleton with the one difference it asks for: no summary — it has one
picture and a line or two around it. `Strip:Breadcrumb` opens the page with where the planche sits (the
listing, its serie, itself), and `Strip:Previous`/`Strip:Next` lay the way to its neighbours over the
planche itself, as GalleryBundle does over a photo: they fade out at rest where there is a pointer to bring
them back, and stay on where there is none.

No edition opens a section of its own: an edition says what the book comes out under, and the pages a
reader leafs through belong to the book. `Book:Extracts` shows them all, in UiBundle's slider, and the
flipbook turning them (`Book:Flipbook`) sits under the hero outside any card — it shows the book, it is no
section to be announced. Every edition a book holds is named on one line of `Book:Informations` instead,
under `book_edition_label()` — the site's own word for it.

A book holding a recording is listened to on `Book:Podcasts`, a section like any other: the first of those
recordings in an `<twig:c975LUi:Audio:Audio>`, then the files it is downloaded as, then the audio stores and
podcast applications it is published on. The player no longer follows the reader down the page — one card
holding the three ways of listening, where the player used to trail at the end of the page.

The language switch is `book_translations(book)` (`Twig\BookTranslationExtension`), which reads the family
from whichever end the visitor arrived at: `Book::getTranslation()` walks a book's children alone, so it
answers from the original and answers nothing from a translation — the page in the other language offered
no way back. Each one is named by `book_language_label()`, its own endonym rather than the word the current
book's language uses for it, and a language this bundle holds no word for prints as its code.

The same family is declared to search engines by `book_alternates(book)` (`Twig\BookUrlExtension`), the
`alternates` map `display.html.twig` hands the layout, which writes one `<link rel="alternate" hreflang>`
per language beside the canonical. Every version names the whole group, itself included — a page leaving
itself out declares a group it is not part of — in absolute urls built from `site-url`, since a hreflang
group is read from another site than the one serving it. A book no one has translated declares nothing:
its canonical has already said everything there is to say about it.

A book carries three images as a whole rather than as one of its versions, each uploaded on a field of its
own under the **Page** tab of its screen: its **first cover**, its **fourth**, and the **backdrop** its
page opens on (`Book::getCovers()`, `getBackCovers()`, `getBackgrounds()`, rows of `Form\BookCoverType`).
The field a file is dropped on is what says which of the three it is — the kind is set by `addCover()` and
its two siblings — so a site never has to name any of them in the vocabulary it declares
(`getMediaKinds()`), and an editor is never asked to pick the right word from a list.

What tells them from the pages, the recordings and the flipbooks the book also holds is their **kind**,
which those three fields set themselves. A site already storing a `cover` of its own — a 712x400 still
built for social cards — keeps it under the kind it gave it, and stays out of these three fields.

`book_media(book, kind)` reads one of them, and `book_cover(book)` is what stands for the book wherever it
is named — its page, its card, the social card built on it: the cover uploaded on that field first, then,
for a book given none, the first *image* the book holds and not its first file — a catalog storing a
recording or a flipbook before its cover would otherwise put an `mp3` in an `<img>`. A site knowing better
which of its files stands for the book still hands it to `Book:Hero` as `cover`.

Given a fourth cover, the hero **turns the cover over on a click**, front and back, rather than stacking the
two: `Book:Hero` renders UiBundle's flip card — its `flip-card` classes and its `flipCard` Stimulus
controller, not its component, which reads `Media` entities where a hero is handed plain paths. The face's
own outline, padding and paper are undone in `sass/_book.scss`: a cover is printed to its own edge, and the
corners and the shadow belong to the image. A book with no fourth cover keeps the plain `<img>` it always
had. Given a backdrop, the hero paints it behind a veil (`book-hero--has-bg`) as a real `<img>` and never as
a CSS `background-image`, a nonce-based `style-src` covering no style attribute.

Which versions a book holds is read off its editions rather than ticked by hand: `Book:Hero` prints one
badge per `BookEdition`, filled for one already out and outlined for one only announced, the word carrying
the information and the shape only repeating it. A version the book does not hold yet stays on the page:
for a reader that is an answer, not a gap.

A book's page carries **what its readers wrote about it**, under the blocks composed at its foot: the
reviews left on it and the fold leading to the form (UiBundle's own `ui_reviews_section()`, filed under
the `book` owner type). Nothing shows until the site enables `ui-enable-reviews` — the function answers
an empty string and the section stays away. It is held in the very cache the blocks above it are held in,
tagged so that publishing a review empties it: nothing in it belongs to one visitor. None of the six
public pages sets a browser cache header of its own all the same.

`Service\BookFavoriteItemProvider` is what names a book wherever UiBundle holds nothing but an owner type
and an id — the heading of the page a review is left from, an entry of a reader's wishlist. It answers
through `BookRepository::findPublishedByIds()`, so an id a visitor may not reach — a book in the trash,
one not published yet — is simply absent rather than named. A book removed for good takes its reviews
along with its ratings (`Service\BookTrashManager`).

#### Editing a section from the page itself

Whoever the back-office lets in (`site-role-editor`, the role the three CRUD screens now sit behind —
the raw table exports and the two actions of the trash stay at `site-role-admin`) hovers a
pencil over each section of a display page: it opens that entity's EasyAdmin edit screen straight on the
field the section is written in — the summary on `summary`, the videos on the `videos` collection, the
extracts, the editions, the shops and the podcasts on the `editions` collection, and so on. It is
UiBundle's own overlay (`assets/js/block-edit-overlay.js`, the button it floats over anything carrying
`data-block-edit-url`) and its own `focusField` query param (`assets/js/field-focus.js`, which opens the
right tab, scrolls to the field and focuses it) — nothing of it is written here beyond the map of
anchor to property, `book_edit_urls(book)`, `serie_edit_urls(serie)` and `strip_edit_urls(strip)`
(`Twig\BookEditUrlExtension`). A section written on another screen than the entity's own — a serie's list
of books, which each book carries itself — is deliberately left without a pencil rather than given one
leading nowhere. A site overriding a display component keeps its pencil by passing the `editUrl` it
receives down to the card it prints. The blocks composed at the foot of a page hover the same pencil,
through `Management\BookBlockEditUrlProvider` — UiBundle's own `focusBlock`, which opens that block's
row in the accordion of the entity's form.

Every template is overridable in `templates/bundles/c975LBookBundle/`. **A site overriding one of these
must check its props**: `Book:Videos`, `Book:Presses`, `Book:Marketings` and `Book:Serie` now take the
`book` itself rather than the collection, so they can print their own title in the book's language, and
`Book:Links` takes a `lang`. The language switch moved from `Book:Informations` to `Book:Hero`, beside the
title.

#### Rephrasing a summary

The `summary` of a book, a serie and a planche is typed in UiBundle's own Trix editor
(`c975L\UiBundle\Form\TrixEditorType`) rather than EasyAdmin's `TextEditorField`: its widget is where
**Donovan**'s rephrase button is wired, EasyAdmin's own rendering through a different form block, which
would leave these three the only rich-text fields of the back office without it. The button appears once
the rephrase feature is configured in UiBundle, and stays absent otherwise.

### Editions

A book is published in more than one form, and each has its own identifier: the paperback, the ebook, the
audiobook, the illustrated edition, the translation's own audiobook. They used to be three `isbn_*` columns
on the book, which said nothing of what each one was and could not hold a fourth. Each is a `BookEdition`
row now, the same move `BookLink` made for the stores:

```twig
{# every edition: its name, its ISBN, what it is physically and how many pages it holds #}
{% for edition in book.editions %}
    {{ book_edition_label(edition, book.language) }} : {{ edition.isbn|isbn }}
    {% if edition.format %} - {{ edition.format }}{% endif %}
{% endfor %}

{# one edition by name #}
{{ book.getEdition('audio')?.isbn }}
```

**An edition says what the book comes out under and nothing more** — its ISBN, its size, its page count.
The files and the platforms belong to the book and are edited under the gesture they serve: the recording
under "Listen" beside the podcast apps, the stores under "Buy". A paperback and an ebook of the same story
are sold at the same addresses, and neither has pages of its own to upload.

**Each edition is edited in a panel of its own**, unfolded in the "technical informations" tab of the book
(`BookEditionType`, an accordion entry). Deleting an edition takes nothing else with it.

**The date is the book's, and it is the only one**: an ISBN is reserved long before the book it names, and
a book with no date, or with one still to come, says "à paraître" on its page. That single date is what a
catalog listing published books asks (`BookRepository::publishedQueryBuilder()`).

**Upgrading from the `isbn_*` columns** — the editions are minted from the columns, which are then dropped.
Adjust the kinds to the ones your site declares:

```sql
INSERT INTO book_edition (book_id, kind, isbn, position)
    SELECT id, 'paper', isbn_paper, 0 FROM book_book WHERE isbn_paper <> '';
INSERT INTO book_edition (book_id, kind, isbn, position)
    SELECT id, 'digital', isbn_digital, 1 FROM book_book WHERE isbn_digital <> '';
INSERT INTO book_edition (book_id, kind, isbn, position)
    SELECT id, 'audio', isbn_audio, 2 FROM book_book WHERE isbn_audio <> '';
```

### Duplicating a book, a serie or a strip

The index and the edit screen of each of the three carry a **Dupliquer** action (`site-role-editor`, same
role as editing): it copies the row with everything hanging off it, saves it and opens the copy for editing
straight away. A book brings along its editions, its files, its platform links, its press, its videos, its
marketing and its blocks — each file and each link copied exactly once. It **leaves the versions behind**:
a duplicate is a new book the editor then qualifies, where chaining one book behind another is the
versions gesture's own doing. A serie brings its covers, its logos and its blocks, and **leaves its books and its
strips with the original**: they belong to the serie they were published in, and duplicating one is its own
decision, taken book by book. A strip brings its plates and its blocks.

The copy is a new row from top to bottom: its title carries a `(copie)` suffix, its slug is freed of any
collision (`UniqueSlug`), and every uploaded file is copied on disk rather than shared with the source —
the copy runs back through Vich's normal pipeline (`ReplacingFile`), so deleting one of the two never takes
the other's image with it. Everything else is carried over as it stands, an ISBN included: the copy is made
to be reworked, and what has to change is the editor's call. `Service\BookDuplicator` builds that graph and
persists nothing, so a site can call it from a command or a listener of its own.

### Publishing a new version of a book

A book rewritten, revised or newly illustrated is not one more edition — every edition exists for both
texts. It is a book of its own, and the **Publier une nouvelle version** action of the book's screen
(`site-role-editor`, beside the copy) is what brings it out: the book **keeps its address and its
identity** — its id, its slug, its readers' ratings, its blocks, its translations — and a twin is born
carrying what came out so far, its editions among them. The book starts again with editions to fill.

The address is the point: it is the one outside links carry and the one a search engine indexes, so it has
to lead to the most recent text. The twin only leaves the catalog (`Book::$newerVersion`): it keeps its
page, its ISBN and its place in the sitemap, and a reader reaches it from the search or from the book
replacing it, whose informations name it. Both ends of the link read — the old page names the new as
readily as the reverse (`book_versions()`, `Twig\BookVersionExtension`).

A third version chains onto the second rather than beside it: a book already replaced is not replaced
again, it is the version replacing it that comes in versions in its turn. `Service\BookVersionPublisher`
builds that graph, so a site splitting an already published catalog can call it from a command of its own.

### Trash, redirects and 410

**Deleting takes a row off the site, it does not lose it.** A serie, a book or a strip deleted from its
index goes to the **trash** (`isDeleted`): the row, its files, its versions and its blocks stay exactly as
they were, and its public page answers **410 Gone** rather than the 404 an url that never existed gets —
which is what a search engine drops an indexed page on. Each index carries a **Corbeille** toggle listing
what left the site, where every row offers **Restaurer** and **Supprimer définitivement** (both
`site-role-admin`, the rest of the screen staying `site-role-editor`). A trashed row leaves every public
listing, the search components, the sitemap and the serie autocomplete of the book and strip forms — the
repository queries filter on the column, so a site calling them through `BookService`, `SerieService` or
`StripService` gets that for free.

**A row in the trash still owns its slug** — `UniqueEntity` counts it like any other, so that url cannot be
taken by something else while it sits there, and a restore never collides. An admin who cannot reuse a slug
should look in the trash for what still holds it.

**A serie is refused the trash while it still holds a book or a strip**, trashed ones included: what it
holds would otherwise stay on the site naming a serie that is not on it any more. Move them to another
serie — or remove them — and the serie follows.

**Removing for good leaves the url answering.** The 410 the trash serves only lasts as long as the row can
be restored, so `deletePermanently()` writes a `Redirect` row with `gone: true` on the path the row used to
answer, which ConfigBundle's `RedirectSubscriber` keeps serving for good. Redirects that pointed at that
url become `gone` rows too rather than dangling, and a destination an admin set up by hand is left alone —
it says more than a dead end. A book other books name as their original detaches them on the way out, that
column being the one thing Doctrine doesn't clear on its own.

**Renaming keeps the old url reachable**: changing a slug creates (or refreshes) a permanent `Redirect`
from the old public path to the new one, and drops the row pointing the other way, so renaming back and
forth can't build a loop. A site serving a family elsewhere — an empty url prefix, see
[public routes](#routes) — gets no redirect row at all: there is no url of ours to speak of.
`Service\BookTrashManager` holds all of it, so a command or a listener of your own can call the same thing.

The 410 itself is rendered by the app's own error template (`templates/bundles/TwigBundle/Exception/error410.html.twig`);
SiteBundle ships one, and a site without it gets Symfony's default error page.

### Customizing the catalog

What one site adds to its books is declared through `BookCustomizationProviderInterface`, not by overriding
`BookCrudController` — the screen stays the bundle's, and an app upgrading gets its improvements:

```php
namespace App\Management;

use c975L\BookBundle\Contract\BookCustomizationProviderInterface;

class BookCustomizationProvider implements BookCustomizationProviderInterface
{
    // The kinds of files this site's books hold, offered by the media form - none declared leaves the field out of it
    public function getMediaKinds(): array
    {
        return ['page' => 'Page', 'audio_mp3' => 'Audio MP3', 'trailer' => 'Bande-annonce'];
    }

    // The editions this site publishes - none declared falls back to the bundle's paper/digital/audio
    public function getEditionKinds(): array
    {
        return ['original_digital' => 'Originelle numérique', 'illustrated_paper' => 'Illustrée papier'];
    }

    // How this site lays out a book's page: the order, the words, the look, and what it adds inside a card
    public function getSections(): array
    {
        return [
            'resume' => [
                'anchor' => 'id.idea', 'label' => 'label.idea', 'domain' => 'messages',
                'icon' => ['images/lightbulb.svg', 'white', 24, 24],
                'animation' => 'slide-in-right',
                'template' => 'story/_idea.html.twig',
            ],
            'shops' => ['label' => 'label.shops', 'domain' => 'messages', 'template' => 'story/_shop_button.html.twig'],
            'edition' => ['animation' => 'slide-in-left'],
            'informations' => ['icon' => ['images/key.svg', 'white', 24, 24]],
        ];
    }

    // A plain form type mapped on Book::$data, holding the fields no other site has - null for a site adding none
    public function getDataFormType(): ?string
    {
        return StoryDataType::class;
    }
}
```

#### Laying out the page

`getSections()` is what lets a site follow the bundle's page rather than draw its own. The keys are the
sections themselves — `resume`, `apercu`, `extracts`, `edition` (which dresses every edition's section at
once), `shops`, `podcasts`, `presse`, `marketing`, `serie`, `informations` — and **the order of the array is
the order of the page**, a key left out coming after the declared ones, where the bundle puts it. A site
declaring nothing gets exactly the page it always had.

Per section: `anchor` (the id the summary jumps to) and `label` (the card's title) are looked up in `domain`
— the `book` catalog unless the site names its own — in the book's own language, an untranslated one
printing as it stands. `icon` is what `c975LUi:Card:Card` takes. `animation` is the very entrance effect a
block carries (UiBundle's `animate-scroll.js`, same `data-animation`), the page mounting that controller
only when a section asks for one. `template` is a fragment of the site's, included **inside** the card with
`book` and `section` at hand — which is where a shop button, a smartlink or a field of the site's own goes,
rather than in an override of the whole component.

A label is passed to the translator, so it can be a key of the `book` domain as well as plain words: a
string the catalog holds no entry for is printed as it stands.

The fields of `getDataFormType()` are stored in `Book::$data`, one JSON payload rather than a column each —
same reasoning as UiBundle's `Block::$data`: what a single catalog needs is a form type it declares, no
schema migration for every app running this bundle. The form type is a plain one, mapped on the array:

```php
class StoryDataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('idea', TextType::class, ['label' => "L'idée de", 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'empty_data' => []]);
    }
}
```

Read back by name, never as a raw key spelled out in a template: `book.getDataValue('idea')`.

**Anything the database itself has to filter, sort or join on stays a real column** — an ISBN is a
`BookEdition` row, a publication date is a column. `data` holds what only one site displays.

### Links

Where a book is read, listened to or watched is held by `BookLink`, one row per platform, rather than by a column per store as it used to be. The row holds the address itself and the platform as a plain string — the site's own vocabulary, the bundle's `BookLinkKind` standing in as the default catalog when a site declares none:

```twig
{# every store, every podcast platform, as its own card #}
<twig:c975LBook:Book:Shops book="{{ book }}" />
<twig:c975LBook:Book:Podcasts book="{{ book }}" />

{# or one platform, asked for by name #}
{{ book.link('epub_kobo').url }}
```

`url` holds the whole address, pasted as the platform hands it over, the way SiteBundle's `CollectionItem` holds one. What an address carries beyond the book — an affiliate identifier, a country, a format anchor, a podcast naming its show as well as its episode — belongs to the site and is kept that way, the bundle rebuilding nothing.

**Google Play affiliation** — the one identifier a site would otherwise paste into every Google Play address is held once, by the `book-gplay-affiliate` setting (sensitive, so it is stored encrypted and masked in the back-office). `book_link_url()` adds it as `PAffiliateID` to an address on `play.google.com`, and to that host only:

```twig
{# the stored address, plus the configured affiliate on a Google Play one #}
<twig:c975LUi:Image:Link url="{{ book_link_url(link) }}" … />
```

The stored address opens the right page on its own — the setting left empty changes nothing, and an address already carrying its own `PAffiliateID` is never overwritten. Printing `link.url` directly hands over the address exactly as it is stored, affiliation included or not.

**Declaring your own platforms** — a site selling in a shop the bundle never heard of names it in its provider (see [customizing the catalog](#customizing-the-catalog)), each platform saying what it is called, which card it prints in (`epub`, `audio`, `podcast`, `video`) and the icon standing for it:

```php
public function getLinkKinds(): array
{
    return [
        'epub_bookshop' => ['label' => 'Bookshop', 'group' => 'epub', 'icon' => 'images/bookshop.svg'],
    ];
}
```

Declaring one replaces the bundle's catalog whole. A template reads a link through `book_link_label()`, `book_link_icon()`, `book_link_url()` and `book_links_of(book, 'epub')` — the entity answers none of the three, the vocabulary being the site's and not the row's.

### ISBN filter

Format a raw ISBN string in Twig:

```twig
{{ book.isbn|isbn }}
{# Outputs: 979-10-92030-14-3 #}
```

### Blocks

`Book`, `Serie` and `Strip` implement UiBundle's `HasBlocksInterface`, so their pages are composed in the back-office with the kinds of UiBundle (`hero`, `text_section`, `image`, `slider`, `cta_band`…) — no template to write. The detail templates render the collection:

```twig
<twig:c975LUi:Blocks:Blocks blocks="{{ book.blocks }}"/>
```

A saved block moves from one container to another by drag and drop, `BookBlockOwnerResolver` being what lets UiBundle's move screen find the book, serie or strip holding it.

A block hovers the same editing pencil as the sections above it (see *Display pages*): `BookBlockEditUrlProvider` answers UiBundle which screen composes a given block row, and the link opens it on that very row (`focusBlock`).

The bundle also ships five block kinds of its own. Four put a selection of the catalog on any page of the site — `book_series`, `book_books`, `book_to_be_published` and `book_serie_strips`. Set to UiBundle's `compact` variant, they print a book at a thumbnail's width, with its cover, its title and its language and without its summary.

The fifth, `book_reader`, reads an illustrated album page by page along its recording. Its medias are the album's pages in order, then the audio file; its `cues` say at which second of the recording each page is turned. The voice is the clock: it turns the pages, and a page turned by hand moves the playhead to that page's cue. Left without cues, the pages are turned by hand alone. It drives UiBundle's `slider` through the slider's own dots, so the two stay independent.

```twig
{# Outside a composed page - a story rendered from an entity, say - the component is called directly #}
<twig:c975LBook:Reader:Reader media="{{ pages }}" audio="{{ recording }}" id="reader" cues="{{ [{page: 1, start: 0}, {page: 2, start: 14.5}] }}"/>
```

### Structured data

A book's, a serie's and a strip's page publish their schema.org graph as JSON-LD, built by `BookSnippetBuilder` from the fields those pages already show: author, illustrator, language, publication date, page count, age range, one `workExample` per released edition, and the volume's rank in its serie (`isPartOf`/`position`, `hasPart` on the serie side).

An edition carries the ISBN and the page count of its own form, an audio one being an `Audiobook` rather than a `Book`, and the addresses the book is found at under the gesture that edition serves (`sameAs`) — the podcast apps for a recording, the bookshops for a printed or digital one. A translation is not an edition: it is a book of its own, paired with the one it translates through `translationOfWork` and `workTranslation`, at the url `BookPublicUrlResolver` spells for it.

A strip is a `ComicStory` — its characters, its rank in its serie and the address it first appeared at (`sameAs`) — where a book of that same serie is a `Book`, the two being read and indexed apart. One not published yet publishes nothing.

Overriding a display template keeps the markup, which is a Twig function rather than a template of its own:

```twig
{% set jsonLd = book_json_ld(book, ogImage, url('book_display', {'slug': book.slug})) %}
{% if jsonLd %}
    <script type="application/ld+json">{{ jsonLd }}</script>
{% endif %}
```

`serie_json_ld(serie, ogImage, url)` and `strip_json_ld(strip, imageUrl, url)` are called the same way.

Price and availability are deliberately absent: they are an `offers` node, and they belong to whoever sells the book.

### Sitemap

The urls are declared by `BookSitemapProvider` (ConfigBundle's `SitemapProviderInterface`): index pages plus individual entries for all published books, all series, and all published strips. Nothing to register — the provider is picked up automatically.

Each one is built by `BookPublicUrlResolver`, which generates the path through the router and prefixes it with the configured `site-url`, so the sitemap declares the exact urls the routes answer to: it follows the prefixes of `BookRoutePrefix`, and leaves out a family whose prefix is empty — those pages are not served here at all. Nothing is declared before `site-url` is set, a sitemap accepting no relative url.

The bundle supplies urls and nothing else: `public/sitemap-book.xml` and the site's `public/sitemap-index.xml` are written by ConfigBundle, which collects every installed bundle's provider:

```bash
php bin/console c975l:sitemaps:create
```

That's the one to schedule — it is also the "Create sitemaps" shortcut of the dashboard.

Those same urls are also **health-checked** for free, with `c975l/site-bundle` installed: every declared url gets the content-quality checks (title/description length, missing `<h1>`, Open Graph share tags, images without `alt`, broken links) under its own `urls-book` kind on the Health check dashboard, schedulable apart from the rest:

```bash
php bin/console c975l:health-check:run --kind=urls-book
```

The same provider carries the `title` and `description` ConfigBundle's `SeoFilesWriter` builds `public/llms.txt` from — a `## Book` section listing the indexes, the published books and the series. A strip's page carries no title there on purpose, an index of plates being what the sitemap already is.

### Health check

The urls above are the site's own. The ones a catalog sends its readers *away* to — a store, a podcast app, a video platform (see [links](#links)) — are checked by `BookLinkHealthCheckProvider`, under the `book-links` kind: a store that closed, a podcast feed that moved or a mistyped address surfaces on the Health check dashboard rather than on a reader's screen.

There is nothing to schedule and no command to add: ConfigBundle already runs `c975l:health-check:run --frequency=weekly` from the site's `MaintenanceSchedule`, and a provider is the whole of what a bundle contributes. One run on demand, this bundle's links alone:

```bash
php bin/console c975l:health-check:run --kind=book-links
```

Every book is checked, published or not — a book to be published shows its pre-order links, and those are the ones worth catching before its release day. Each row names the book and the platform it was declared on, and links to the book's edit screen, opened on its editions.

A platform answering `401`, `403`, `405` or `429` is reported **skipped**, not broken: most stores turn down a `HEAD` request carrying no browser behind it, and there is nothing there for an editor to fix. `404`/`410`, any other error code, and a host that never answered at all are reported as errors, the http code being kept in the row's details. The provider enumerates every link each run, so an address that is deleted or corrected drops off the dashboard instead of leaving its last red row behind.

### Export / import the catalog

`SerieExportProvider`, `BookExportProvider` and `StripExportProvider` plug the three families of this bundle
into ConfigBundle's **Export sync (everything)** dashboard shortcut — one zip carrying the whole catalog,
files bundled in, re-uploaded on another site or environment through its **Import content** screen (see
`SerieImportProvider`, `BookImportProvider`, `StripImportProvider`). Nothing to register: implementing the
interfaces is what tags them.

A subset travels the same way: the **"Export selection"** batch action of each of the three index screens
zips the checked rows only, through the very same providers (`serializeIds()`), and the archive is re-uploaded
on the same screen. Both are restricted to `site-role-admin`, like the raw table dumps beside them.

Ids never need to match between the two sites. Every row is matched on what it actually answers at — a serie,
a book and a strip by their slug, a version by its kind within its book, a platform by the version and the
kind together, a file by the name it is served under. A book naming a serie this environment doesn't hold yet
has it created on the fly, so the three kinds import in whichever order the archive lists them, and a book
translating another is bound once the whole archive has been read.

**The trash flag travels too**: a row exported out of the trash comes back to the trash, not onto the site,
and a sync mirrors its source rather than republishing what an admin had taken down (see
[trash, redirects and 410](#trash-redirects-and-410)).

Blocks travel with their own medias and are replaced wholesale on import, the same way `PageImportProvider`
replaces a page's. Files, on the other hand, **travel with their names** and are laid straight back under
them: the upload pipeline is skipped entirely, so an imported catalog answers at the very same urls, and
re-importing an archive over the catalog it came from rewrites nothing that is already on disk. A name coming
out of an archive is only honoured under `public/medias/book/`, as a plain relative name — anything climbing
out of it is refused. A media whose file has left the disk is dropped from the export rather than travelling
as a broken reference; one standing for a YouTube url holds no file and travels all the same.

### Backup

`BookBackupPathProvider` declares `public/medias/book` to ConfigBundle's backup, which mirrors it off-server: the covers, extracts, press clippings and marketing files are the only content of this bundle that neither a git clone nor a database dump brings back. Nothing to register.

---

> [!TIP]
> If this project **helps you save development time**:
>
> - [**star** it on GitHub](https://github.com/975L/BookBundle) — helps others find it
> - [**open an issue**](https://github.com/975L/BookBundle/issues/new) to share how you use it — genuinely useful feedback
>
> And if you'd like to support the work directly, the **Sponsor** button at the top of the GitHub page is there for that. Thank you!

---

## AI agent skills

The package ships three skills of its own, written for the coding agent of the site installing this
bundle rather than for someone modifying it. Point your agent at them:

```text
vendor/c975l/book-bundle/skills/
```

| Skill | Covers |
| --- | --- |
| `c975l-book-display-pages` | the six public pages, their hero and their sections, the components a site overrides, the editor's pencil, the CSS tokens |
| `c975l-book-customization` | the two contracts a site declares itself with — its vocabulary and what its catalog lists — the platforms, and the url prefixes |
| `c975l-book-lifecycle` | publishing a new version, duplicating, the trash and its 410/301 answers, which repository read answers what, the zip export/import |

They hold what an agent gets wrong when left to its own habits — that an edition is a format and never a
version of the text, that a url prefix is admin-editable and may be emptied, that a setting goes in
`config/configs.json` and not in `.env`, that a section is read by its key and not by its anchor, that a
platform stores its whole address — alongside the routes, the entities, the config slugs, the blocks, the
Twig functions and the components, each named as it actually is in the sources. `tests/SkillsTest.php`
checks every one of those names against `src/` on each run, so a rename cannot leave a skill confidently
wrong.

Nothing is installed, nothing is copied into your project: the files sit in `vendor/` like any other part
of the package and follow it at each `composer update`. A user of Claude Code wanting one to load by
itself symlinks it into their own skills directory:

```bash
ln -s ../../vendor/c975l/book-bundle/skills/c975l-book-display-pages .claude/skills/c975l-book-display-pages
```
