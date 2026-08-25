---
name: c975l-book-display-pages
description: "Use this skill when working on the public pages of a catalog built on the c975L BookBundle — the page of a book, of a serie and of a planche, their hero, their sections and the order they come in, the summary of anchors, the cards a listing prints, the breadcrumb and the arrows a planche is browsed with, the editor's pencil floating over a section, and the CSS tokens a site retunes them all with. Triggers on: book_display, serie_display, strip_display, book_index, serie_index, strip_index, book_sections, serie_sections, book_section_template, BookSectionsExtension, Book:Hero, Serie:Hero, Strip:Hero, Book:Resume, Book:Extracts, Book:Podcasts, Book:Videos, Book:Shops, Book:Informations, Book:Card, Book:Flipbook, Book:Crowdfunding, Strip:Card, Strip:Cards, Book:Books, Serie:Series, infinite, infiniteScroll, load_more, Pagination, Paginator, Strip:Characters, Strip:Previous, Strip:Next, Breadcrumb, _section.html.twig, toc-target, card--compact, book-page, book-hero, strip-card, book_edit_url, book_edit_urls, serie_edit_urls, strip_edit_urls, BookEditUrlExtension, book_cover, book_media, book_medias_of_kind, book_audio_medias, book_translations, book_language_label, book_alternates, book_versions, BookUrlExtension, BookTranslationExtension, BookVersionExtension, book-strip-card, book-confetti, book-rating, sass/_variables.scss, themes/book.css."
---

# c975L BookBundle — display pages

> A page is its hero, then a column of sections. Which sections there are is answered once, by `book_sections()` / `serie_sections()`, and read twice — to build the summary of anchors and to render the page — so a section is never offered in the summary without being on the page.

**Package:** `c975l/book-bundle` · **Bundle:** `c975L\BookBundle\` · **Twig namespace:** `@c975LBook` · **Translation domain:** `book`

**Key source paths** (relative to the package root):
`src/Twig/BookSectionsExtension.php`, `src/Twig/BookEditUrlExtension.php`, `src/Twig/BookUrlExtension.php`, `src/Twig/BookTranslationExtension.php`, `src/Twig/BookVersionExtension.php`, `src/Controller/BookController.php`, `src/Controller/SerieController.php`, `src/Controller/StripController.php`, `templates/book/display.html.twig`, `templates/book/_section.html.twig`, `templates/book/index.html.twig`, `templates/serie/display.html.twig`, `templates/serie/index.html.twig`, `templates/strip/display.html.twig`, `templates/strip/index.html.twig`, `templates/components/`, `sass/_variables.scss`, `sass/_book.scss`, `scaffold/assets/styles/themes/book.css`

**Related skills:** `c975l-book-customization` and `c975l-book-lifecycle` in this same bundle, and `c975l-blocks`, `c975l-media`, `c975l-ui-assets` in UiBundle beside it.

## The six pages

| Route | URL | Answers |
| --- | --- | --- |
| `book_index` | `/livres` | the catalog, growing on scroll |
| `book_display` | `/livre/{slug}` | one book — 410 for a trashed one, 301 from its number |
| `serie_index` | `/series` | the series telling books, growing on scroll |
| `serie_display` | `/series/{slug}` or `/strips/{slug}` | one serie, under the index listing it |
| `strip_index` | `/strips` | the series telling planches, not the planches, growing on scroll |
| `strip_display` | `/strip/{slug}` | one planche |
| `book_shortcut` / `strip_shortcut` | `/b3`, `/s3` | 301 to the slug |

Every prefix is a ConfigBundle setting (`book-route-books`, `book-route-book`, `book-route-series`,
`book-route-strips`, `book-route-strip`, `book-route-book-shortcut`, `book-route-strip-shortcut`) — see
`c975l-book-customization`. **A prefix emptied means the site serves that page itself**, and the route
answers nothing: anything linking to it must ask `BookPublicUrlResolver::resolvePath()` first, which
returns null rather than throwing. `book_path()`, `serie_path()` and `serie_url()` already do.

`serie_display` is one route for both families: a serie's own `SerieKind` decides which index a serie
sits under, and one asked under the other segment answers a 301 to its own.

## The skeleton of a page

```twig
{# what the page holds, answered once #}
{% set sections = book_sections(book) %}

<twig:c975LBook:Breadcrumb .../>     {# where the page sits, and the way back out #}
<twig:c975LBook:Book:Hero book="{{ book }}" .../>   {# the page's only h1 #}
<twig:c975LBook:Book:Resume book="{{ book }}"/>     {# no title, no anchor: not a destination #}
{% for section in sections %}…{% endfor %}
```

**The hero carries the only `h1`** — the layout's own title block is left empty by the three display
templates, which render `Book:Hero`, `Serie:Hero` or `Strip:Hero` inside the container instead. A second
`h1` anywhere on these pages is a bug.

**The summary is no section.** It is the sentence the page opens on, printed by `Book:Resume` /
`Serie:Resume` under the hero and outside the sections grid, with no title and no anchor. Neither a book's
page nor a serie's carries a summary of anchors any more: the hero's verbs open what a reader looks for,
and the page keeps its whole width. `sass/_book.scss` opens the two-column layout on
`.book-page:has(> .book-page__toc)` alone — a page with no toc that grows one gets two columns back.

## The sections

| Owner | Keys, in the bundle's order |
| --- | --- |
| a book | `extracts`, `podcasts`, `apercu`, `crowdfunding`, `shops`, `presse`, `marketing`, `informations` |
| a serie | `books`, `strips` |

A section is present only when the book actually holds something for it, `informations` excepted — a book
with no field filled in still says when it comes out and who wrote it. Each entry carries `key`, `anchor`,
`label` and, when the catalog holds one, `intro`. **`key` is the name everything else uses** — the site's
declaration, the pencil's map of fields; `anchor` is only what the page writes as an `id`, and a site may
rename it. Reading a section off its anchor is what silently loses a button when a site renames one.

Every section is rendered through `templates/book/_section.html.twig`, which owns the anchor, the name,
the icon, the entrance effect, the site's own fragment and the pencil — a section component writes nothing
but what goes inside:

```twig
{% set body %}…{% endset %}
{{ include('@c975LBook/book/_section.html.twig', {book: book, section: section, editUrl: editUrl, body: body}) }}
```

The contents arrive as an already-rendered `body` string and **never as a block an `{% embed %}` overrides**:
a block declared by an embed never reaches the inside of a component's slot, and the card came out titled
and empty, which neither the Twig lint nor a template test can see.

`form` decides the shape, not the contents: `text` and `framed` are written as a plain `<section>` (prose
carries no frame, so those two do the anchor, the title and the pencil themselves), anything else is a
`c975LUi:Card:Card`. Every section wears UiBundle's `toc-target`.

## The components a site overrides

They all live in `templates/components/` and are overridden in
`templates/bundles/c975LBookBundle/components/`. The ones a site touches most:

| Component | Prints |
| --- | --- |
| `<twig:c975LBook:Book:Hero>` | the opening — cover (flipped over on a click given a fourth), backdrop, eyebrow, title, the four verbs |
| `<twig:c975LBook:Book:Resume>` | the sentence under the hero |
| `<twig:c975LBook:Book:Extracts>` | the pages, in UiBundle's slider |
| `<twig:c975LBook:Book:Flipbook>` | the video turning them, under the hero and outside any card |
| `<twig:c975LBook:Book:Podcasts>` | one card for everything the book is listened to by |
| `<twig:c975LBook:Book:Videos>` | one card for everything it is watched by |
| `<twig:c975LBook:Book:Shops>` | the bookshops, the site's own shop first and in the site colour |
| `<twig:c975LBook:Book:Informations>` | the framed civil status — serie, editions, languages, versions |
| `<twig:c975LBook:Book:Card>` | a book in a listing: cover in portrait, summary, language — `:compact="true"` prints it at a thumbnail's width, without its summary |
| `<twig:c975LBook:Strip:Card>` | a planche, the whole card being the link |
| `<twig:c975LBook:Strip:Characters>` | who speaks, as chips narrowing the serie's listing |
| `<twig:c975LBook:Strip:Previous>` / `<twig:c975LBook:Strip:Next>` | the arrows laid over the planche |
| `<twig:c975LBook:Breadcrumb>` | the trail, for all three pages |

**A component overridden must pass its props through.** `Book:Videos`, `Book:Presses` and
`Book:Marketings` take the `book` itself rather than the collection, so they can print their title in the
book's language; `Book:Book` and `Book:Books` take `editable` and hand `editUrl` down to the card, which
is what keeps the pencil. Dropping either leaves a right page with a wrong language or no pencil at all.

## A listing that grows

The three index pages grow as the visitor scrolls instead of turning pages: UiBundle's `infiniteScroll`
controller fetches the page the listing's own "next" link points at and appends the cards found there.
Nothing is written for it alone — that link is an ordinary `rel="next"` link to the next page, which is
what a crawler follows and what happens without javascript or after a failed fetch.

```twig
<div id="books" data-controller="infiniteScroll">
    <twig:c975LBook:Book:Books books="{{ books }}" :infinite="true"/>
    <p><span data-infiniteScroll-target="count">{{ books|length }}</span> / {{ books.getTotalItemCount }}</p>
    <a href="{{ path(books.route, books.query({'p': books.getCurrentPageNumber + 1})) }}#books"
       rel="next" data-infiniteScroll-target="next" data-action="click->infiniteScroll#load">…</a>
</div>
```

`infinite` is what marks the grid the cards land in (`data-infiniteScroll-target="list"`), and it is the
attribute the controller reads the fetched page through: **only the listing that grows may carry it** —
a search's results sitting on the same page would otherwise answer in its place. `Book:Books`,
`Serie:Series` and `Strip:Cards` take it; a serie's own page grows its planches the same way
(`serie/display.html.twig`).

The count is what is **on screen**, `{{ books|length }}`, because the controller writes it back after each
append from the list it holds — a figure counting the pages behind it would jump backwards.

The page itself comes from `BookService`, `SerieService` and `StripService`, which paginate with UiBundle's
`Paginator` (`c975L\UiBundle\Model\Pagination`, 10 books or series per page, 24 planches). The route and
the query the next page's url is rebuilt from ride on it — `pagination.route`, `pagination.query({...})` —
so a filter or a search the visitor came with survives the jump.

## Reading a book's files and its family

```twig
{{ book_cover(book) }}                    {# what stands for the book: its cover field, then its first image #}
{{ book_media(book, 'flipbook') }}        {# one file of a kind #}
{{ book_medias_of_kind(book, 'extract') }}
{{ book_audio_medias(book) }}
{{ book_translations(book) }}             {# read from either end of the family #}
{{ book_language_label(book.language) }}  {# the endonym, never translated #}
{{ book_versions(book) }}                 {# the other versions, both ends of the chain #}
{{ book_alternates(book) }}               {# the hreflang group, absolute urls #}
```

`book_cover()` falls back on the first **image** and not the first file — a catalog storing a recording
before its cover would otherwise put an `mp3` in an `<img>` — and, failing that, on the cover of another
version of the same text.

## The editor's pencil

`book_edit_urls(book)`, `serie_edit_urls(serie)` and `strip_edit_urls(strip)` (`BookEditUrlExtension`) map
a section's **anchor** to the property it is written in; `book_edit_url()`, `serie_edit_url()` and
`strip_edit_url()` answer one. The overlay itself is UiBundle's (`data-block-edit-url`, `focusField`) —
nothing of it is written here beyond that map. A section written on another screen than the entity's own —
a serie's list of books, which each book carries itself — is deliberately left **without** a pencil rather
than given one leading nowhere.

**A section added to a page must be added to that map**, or its pencil disappears; a field renamed in the
CRUD must be renamed there too, or the pencil opens the wrong collection.

## Styling

Everything is a CSS custom property, declared in `sass/_variables.scss` and shipped commented-out for a
site in `scaffold/assets/styles/themes/book.css`. No colour is offered: a book page is laid on the site's
own ground and ink, read through UiBundle's `--background` / `--text` / `--primary`. The one exception is
`--book-link-tile-*`, a platform's mark being drawn in its brand's colours and needing a light plate.

The pages are mobile first: one column, then two from `1200px`. Four settings change the look without
touching CSS: `book-strip-card` (words first or drawing first), `book-strip-card-icon`, `book-strip-rating-icon`
and `book-confetti`; `book-rating` turns the reader score off for all three families.

`public/css/styles.css` and `styles.min.css` are **generated from `sass/`** — edit the SCSS and rebuild,
never the built files.

## Do not

- **Do not add a second `h1`** to a display page — the hero carries the only one.
- **Do not read a section off its `anchor`** — `key` is the stable name; a site renames the anchor.
- **Do not compute in a template what `book_sections()` already answers** — the summary and the page must agree.
- **Do not pass a section's contents through an `{% embed %}` block** — hand `_section.html.twig` a rendered `body`.
- **Do not give the summary a title or an anchor** — it is not a destination.
- **Do not link to a public route without asking `BookPublicUrlResolver::resolvePath()`** — a prefix may be emptied.
- **Do not drop a component's props when overriding it** — the language and the pencil ride on them.
- **Do not pass a boolean prop as a string** — `compact="false"` reaches Twig as a true string; write `:compact="false"`.
- **Do not mark two listings of one page with `infinite`** — the controller reads the fetched page through that attribute.
- **Do not edit `public/css/*`** — they are built from `sass/`.
- **Do not hardcode a colour or a size in `sass/`** — every one goes through a token.
- **Do not add a section to a page without adding it to `BookEditUrlExtension`'s map.**
