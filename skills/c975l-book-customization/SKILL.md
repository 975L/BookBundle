---
name: c975l-book-customization
description: "Use this skill when a single site has to bend the c975L BookBundle to its own catalog without overriding a controller — the words it gives its file kinds, its editions and its platforms, the sections its book pages hold and in what order, the fields it adds to a book, which rows its catalog page lists and under what name, and the url prefixes of its public pages. Triggers on: BookCustomizationProviderInterface, BookCustomizationRegistry, book.customization_provider, BookCatalogProviderInterface, BookCatalogRegistry, book.catalog_provider, getMediaKinds, getEditionKinds, getContributorRoles, getLinkKinds, getSections, getDataFormType, getBooks, getTitle, book_catalog_title, BookCatalogExtension, BookLinkKind, BookLinkGroup, BookMediaKind, BookEditionKind, BookContributorRole, BookContributor, SerieKind, book_link_label, book_link_icon, book_link_url, book_links_of, book_edition_label, book_role_label, BookLinkExtension, BookRoutePrefix, book-route-books, book-route-book, book-route-series, book-route-contributors, book-route-contributor, book-route-strips, book-route-strip, book-route-book-shortcut, book-route-strip-shortcut, book-catalog-title, book-gplay-affiliate, book-rating, book-confetti, book-strip-card, Book::data, BookDataField."
---

# c975L BookBundle — customizing a catalog

> Two contracts, and the split between them is the point: `BookCustomizationProviderInterface` names a catalog's **vocabulary** — the kinds, the platforms, the sections. `BookCatalogProviderInterface` answers **which rows** a page lists and in what order. A word is not a query, so they are not one interface.

**Package:** `c975l/book-bundle` · **Bundle:** `c975L\BookBundle\` · **Translation domain:** `book` · **Config group:** `book`

**Key source paths** (relative to the package root):
`src/Contract/BookCustomizationProviderInterface.php`, `src/Contract/BookCatalogProviderInterface.php`, `src/Service/BookCustomizationRegistry.php`, `src/Service/BookCatalogRegistry.php`, `src/Enum/BookLinkKind.php`, `src/Enum/BookLinkGroup.php`, `src/Enum/BookMediaKind.php`, `src/Enum/BookEditionKind.php`, `src/Enum/SerieKind.php`, `src/Twig/BookLinkExtension.php`, `src/Twig/BookCatalogExtension.php`, `src/Routing/BookRoutePrefix.php`, `config/configs.json`, `config/services.yaml`

**Related skills:** `c975l-book-display-pages` and `c975l-book-lifecycle` in this same bundle, and `c975l-config` in ConfigBundle beside it.

## The vocabulary contract

One class in the app, tagged `book.customization_provider`, answering the five methods. **Every one of them
may answer an empty array**, which falls back to the bundle's own defaults — a site declares only what it
actually renames.

```php
#[AutoconfigureTag('book.customization_provider')]
class CatalogCustomization implements BookCustomizationProviderInterface
{
    public function getMediaKinds(): array   { return ['page' => 'label.page', 'still' => 'Photogramme']; }
    public function getEditionKinds(): array { return ['paper' => 'label.paper', 'illustrated' => 'Édition illustrée']; }
    public function getContributorRoles(): array { return ['narrator' => 'label.role_narrator', 'colourist' => 'Coloriste']; }
    public function getLinkKinds(): array
    {
        return ['epub_local' => ['label' => 'La librairie du coin', 'group' => 'epub', 'icon' => 'images/coin.svg']];
    }
    public function getSections(): array     { return ['informations' => ['label' => 'Fiche technique'], 'shops' => []]; }
    public function getDataFormType(): ?string { return CatalogBookDataType::class; }
}
```

| Method | Empty array falls back to | Shape |
| --- | --- | --- |
| `getMediaKinds()` | `BookMediaKind` | `kind => label` |
| `getEditionKinds()` | `BookEditionKind` | `kind => label` |
| `getContributorRoles()` | `BookContributorRole` | `role => label` |
| `getLinkKinds()` | `BookLinkKind` | `kind => ['label', 'group', 'icon']` |
| `getSections()` | the bundle's own page | `key => ['anchor', 'label', 'domain', 'icon', 'animation', 'template', 'form', 'field']` |
| `getDataFormType()` | no extra fields | a form class mapped on `Book::$data` |

A **label is a plain word or a translation key**: it goes through the translator in the `book` catalog (or
the `domain` the declaration names), in the book's own language, and an untranslated one prints as it
stands. That is what lets `'Édition illustrée'` and `'label.paper'` sit side by side.

`BookCustomizationRegistry` is what everything reads — never the provider directly. **A kind the vocabulary
does not hold prints as it is stored** (`getLinkLabel()` answers `epub_fnac` for an undeclared `epub_fnac`)
rather than throwing: a forgotten declaration is then visible on the page instead of taking it down.

### Sections

The order of the array **is** the order of the page. A key left out comes after the declared ones, in the
bundle's order, keeping the name and the plain card the bundle gives it. Three things a declaration does
that nothing else can:

- `template` adds a fragment of the site's **inside** the card, with the book at hand. Declared under `hero`
  it is included right under the title instead — where a site says what its own books carry.
- a key that is none of the bundle's own, **carrying a `template`**, is a section of the site's: a rubric one
  catalog holds, printed by that fragment alone, at the rank the declaration gives it. `form` is the shape
  it takes, `field` the property it is written in — which is what gives it the same editor's pencil as the
  bundle's sections (`data` for a rubric held in the site's own fields).
- `animation` is the very entrance effect a block carries, read by UiBundle's `animate-scroll.js`.

## The catalog contract

A different question, hence a different tag — `book.catalog_provider`:

```php
public function getBooks(): ?array  // the rows the index lists, in order. [] is an answer; null hands the page back
public function getTitle(): ?string // the word this site gives its catalog. null keeps the bundle's own
```

**`[]` and `null` are not the same answer.** An empty list is a catalog nothing has come out in yet; `null`
hands the page back to the bundle's own published books. `BookCatalogRegistry` reads the first provider
answering, and falls back on the `book-catalog-title` setting for the word alone — a provider naming the
catalog wins over the setting. `book_catalog_title()` is what the template prints.

Use it when *"the books whose own date has passed"* is not what the site means by out — a catalog reading a
story as out on a date of its own answers here rather than overriding `BookController`.

## Platforms

A platform is a row of `BookLink` carrying a plain string kind and the **whole url** — never an identifier
the bundle recomposes. `BookLinkGroup` says which card it prints in: `epub` is the shops card (the site's
own shop stands there beside the digital bookshops), `audio` and `podcast` the listen card, `video` the
watch card. A kind belonging to no group prints in the "other links" fieldset of the CRUD, so it is never
uneditable.

```twig
{% for link in book_links_of(book, 'epub') %}
    <a href="{{ book_link_url(link) }}">{{ book_link_label(link) }}</a>
    <twig:c975LUi:Image:Icon src="{{ book_link_icon(link) }}"/>
{% endfor %}
```

`book_link_url()` is the stored address plus, **on a Google Play address only**, the `book-gplay-affiliate`
identifier — and never over one the stored address already carries. Nothing else is rebuilt from the book.

## Fields a site adds to a book

`getDataFormType()` returns a plain form type mapped on `Book::$data`, the same way a block declares the
form of its own data. The payload travels whole through duplication, export and import, so a key added
later needs nothing anywhere else.

## Settings

All sixteen live in `config/configs.json`, group **book**, and are read through ConfigBundle:

| Slug | Does |
| --- | --- |
| `book-route-books`, `book-route-book`, `book-route-series`, `book-route-contributors`, `book-route-contributor`, `book-route-strips`, `book-route-strip` | the url prefixes |
| `book-route-book-shortcut`, `book-route-strip-shortcut` | the one-letter short links |
| `book-catalog-title` | the word the catalog page carries |
| `book-gplay-affiliate` | the identifier added to a Google Play address |
| `book-rating`, `book-confetti` | reader scores, confetti on a book's page |
| `book-strip-card`, `book-strip-card-icon`, `book-strip-rating-icon` | how a planche card reads |

**A prefix emptied means the site serves that page itself**: `BookRoutePrefix` then registers no route at
all for it, and everything linking to it must go through `BookPublicUrlResolver::resolvePath()`, which
answers null rather than throwing.

## Do not

- **Do not override a CRUD controller or a repository to rename something** — a word is a declaration.
- **Do not put a query in `BookCustomizationProviderInterface`** — which rows a page lists is `BookCatalogProviderInterface`.
- **Do not answer `[]` from `getBooks()` to mean "I have no opinion"** — that is `null`.
- **Do not read a provider directly** — `BookCustomizationRegistry` and `BookCatalogRegistry` are the reads.
- **Do not throw on an undeclared kind** — it prints as stored, which is what makes the omission visible.
- **Do not store a platform identifier and rebuild its url** — the whole address goes in the row.
- **Do not add a `book-*` slug outside `config/configs.json`**, and do not redeclare one another bundle owns.
- **Do not assume a public route exists** — a prefix may be empty.
- **Do not confuse an edition with a version**: an edition is a format (paper, digital, audio); a rewritten or newly illustrated text is a book of its own — see `c975l-book-lifecycle`.
