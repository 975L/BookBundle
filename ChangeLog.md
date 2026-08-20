# Changelog

## Unreleased

- **BC break** — A `BookEdition` is edited on a screen of its own (`BookEditionCrudController`, scoped to one book through `?book=<id>`), holding its files and its platforms; the book's form lists its editions and links into them (20/08/2026)
- **BC break** — Removed `BookEditionType`, and `BookMediaType`/`BookLinkType` no longer take a `book` option nor offer an `edition` choice — the screen editing them already names the edition (20/08/2026)
- `BookEdition` owns its `medias` and its `links`, `BookEdition::addMedia()` setting the book along with the edition (the path a file is stored under is built on the book's slug) (20/08/2026)
- Deleting an edition deletes its files and its links, the `edition` foreign keys moving from `ON DELETE SET NULL` to `ON DELETE CASCADE` — a consuming app has to migrate its own schema (20/08/2026)
- Added `Form\Type\BookEditionsListType` and `management/book_crud_form_theme.html.twig`, listing a book's editions inside its "technical informations" tab (20/08/2026)
- The book creation tour points at the editions list and at the catalog's own "editions" action, its two media steps dropped along with `label.guided_step_book_creation_media_tab` and `label.guided_step_book_creation_medias` (20/08/2026)
- Removed `label.media_edition`, `label.link_edition`, `label.edition_whole_book` and `label.links-help`; added `label.edition`, `label.position`, `label.edition_medias-help`, `label.edition_links-help`, `label.editions_none`, `label.editions_save_first`, `action.edition_new` and `label.info_book_edition` (20/08/2026)
- `c975l/core-bundle` is required from `^1.12.7`, the version shipping `<twig:c975LUi:Text:Toc>` and the `toc-target` class the display pages read (20/08/2026)
- The words the strip page prints - the publication line, the characters heading, the source button - are translated, and so is an illustrative video's caption (20/08/2026)
- A serie and a book's information line name their language with the same endonyms `Book:Book` uses, a Spanish one reading "Français" until now (20/08/2026)
- The book, serie and strip display pages are laid out mobile first, one column then two from 1200px (20/08/2026)
- Added `Book:Hero`, `Serie:Hero` and `Strip:Hero`, carrying each page's only `h1` — the layout's title block is left empty (20/08/2026)
- Added `book_sections()` and `serie_sections()` (`BookSectionsExtension`), read once to summarize a page and once to render it (20/08/2026)
- A book's versions are printed as badges read off its editions, an announced one shown rather than hidden (20/08/2026)
- `Strip:Navigation` rests against the bottom of the viewport (20/08/2026)
- Added the bundle's first stylesheet — `sass/`, `public/css/styles.min.css` and `Service\StylesheetProvider` tagged `ui.stylesheet` (20/08/2026)
- Added `scaffold/assets/styles/themes/book.css`, offering the `--book-*` shape tokens to a site (20/08/2026)
- The section titles of a book page are translated instead of being written in French in the templates (20/08/2026)
- Added `label.previous` and `label.next`, and `label.summary` is translated in English (20/08/2026)
- **BC break** — `Book:Videos`, `Book:Presses`, `Book:Marketings` and `Book:Serie` take the `book` rather than the collection, and `Book:Links` takes a `lang`; the language switch moved from `Book:Informations` to `Book:Hero` (20/08/2026)
- `Book:Couvertures` and `Strip:Published` are no longer rendered by the display pages, the hero and the extracts slider covering both (20/08/2026)
- Added `BookSectionsExtensionTest`, `SectionAnchorsTest`, `BookPageStyleTest`, `ScaffoldThemeTest` and `StylesheetProviderTest` (20/08/2026)
- A book's extracts and a strip's pages are handed to UiBundle's slider through its `media` parameter, `Media` answering the nine accessors the slider reads — a page holding at least one media used to go down (20/08/2026)
- Added `tests/Entity/MediaTest.php` (20/08/2026)
- The 12 Strip component tags carry the bundle prefix, `<twig:c975LBook:Strip:…>` (20/08/2026)
- `BookEdition::getPages()` returns its own column alone, the book's count moving to `getDisplayedPages()` (20/08/2026)
- `StripImportCommand` renumbers the imported medias' positions sequentially, keeping thumbnail, page then panels in that order once reloaded (20/08/2026)
- A book's language prints `Español` as well, and `translations/messages.es.xlf` is shipped (20/08/2026)
- The book, serie and strip CRUDs offer a "view on site" action, hidden when the family isn't served here (20/08/2026)
- A `BookMedia` and a `BookLink` name the edition they belong to, left empty by a file or a store standing for the book as a whole — the pages of the illustrated edition are no longer a kind of their own, and `BookEdition::getMedias()`/`getLinks()` read what each one holds (20/08/2026)
- `BookCustomizationRegistryTest`'s anonymous class is `readonly` and `BookEditionExtension`'s union type is spaced (20/08/2026)
- Added `strip_json_ld()`, a strip publishing its `ComicStory` graph — characters, rank in its serie and original source included (20/08/2026)
- A book's graph pairs it with its translations, through `translationOfWork` and `workTranslation` (20/08/2026)
- An audio edition is typed as an `Audiobook`, each edition also carrying its name, its language and the platforms publishing it (20/08/2026)
- **BC break** — the three `isbn_*` columns of `Book` are replaced by `BookEdition` (table `book_edition`), one row per published form of the book, each with its own ISBN, release date and page count: an edition announced ahead of its release is a row whose `published` is empty, which no column could say (20/08/2026)
- Added `BookCustomizationProviderInterface`, where a site declares what it adds to the catalog — the kinds its medias and its editions use, and the form of the fields its own books carry — rather than overriding `BookCrudController` (20/08/2026)
- Added `Book::$data`, holding the fields a single site adds to a book as one JSON payload, same reasoning as UiBundle's `Block::$data` — rendered from the form type the site declares (20/08/2026)
- `BookMediaType` uploads any file and no longer images alone, a book's medias being its pages but also its recording, its trailer and its flipbook, and offers the kinds the site names (20/08/2026)
- `book_edition_label()` prints an edition's name, which only the vocabulary knows — a template guessing a translation key off the kind would print the key itself (20/08/2026)
- `BookSnippetBuilder` builds one schema.org `workExample` per edition out, carrying its date and its page count, where it used to emit one per ISBN column (20/08/2026)
- The books are listed by volume number, the latest first (20/08/2026)
- Added `tests/Entity/BookEditionTest.php` and `tests/Service/BookCustomizationRegistryTest.php` (20/08/2026)
- The two `strip/` templates state their `title`, `summarySocialNetwork` and `ogImage`, as the book and serie pages do (20/08/2026)
- Added `tests/ConfigsJsonTest.php`, checking the six `book-route-*` entries and the translation of their labels in the three locales (20/08/2026)
- `BookSitemapProvider` builds its urls through the new `BookPublicUrlResolver`, generated from the routes instead of hand-written (20/08/2026)
- Added `tests/Management/BookSitemapProviderTest.php` and `tests/Service/BookPublicUrlResolverTest.php` (20/08/2026)
- The url prefix of each public route is a ConfigBundle entry (group **Catalogue**), read at each request through `Routing\BookRoutePrefix` — a site serves the catalog in its own language, and an entry left empty takes that family's pages off the site, which `BookSitemapProvider` and `Management\LinkableRouteProvider` follow (19/08/2026)
- The bundle declares its `assets/` folder to the AssetMapper, without which `importmap.php` could not resolve the `controllers.js` entrypoint it names (19/08/2026)
- Added the `book_reader` block kind, an illustrated album read page by page along its recording (19/08/2026)
- The bundle ships its first front-end assets, `assets/controllers.js` and the `book-reader` Stimulus controller (19/08/2026)
- `/strips/character/{character}` is paginated as `/strips` is, and takes the character's slug, `Strip::$charactersSlug` carrying the slugged characters it matches (19/08/2026)
- An unpublished strip returns a 404 instead of an empty page (19/08/2026)
- `Serie::getCovers()` also matches the legacy covers stored with no kind, see the README for the query normalizing them (19/08/2026)
- `Serie:SerieLogo` handles a null serie, a book having none (19/08/2026)
- `BookVideoType`, `BookPresseType` and `BookMarketingType` declare the `book` translation domain, their labels showing as raw keys (19/08/2026)
- `Book:ToBePublished` drops its link to ContactFormBundle's route, which the bundle does not require (19/08/2026)
- Added the `label.spanish` translation, missing from the three catalogs (19/08/2026)
- `StripMedia`, `BookMedia` and `SerieMedia` build their upload path on a not-yet-associated owner (19/08/2026)
- Added `tests/Entity/BookLinkTest.php`, `tests/Entity/BookLinksTest.php`, `tests/Enum/BookLinkKindTest.php` and `tests/Form/BookLinkTypeTest.php` (20/08/2026)
- Added `tests/TranslationCatalogTest.php`, checking that the three catalogs hold the same keys and that the ones the forms name are translated (20/08/2026)
- `BookLink` holds the whole address in `url`, replacing the `value` identifier the bundle used to rebuild an address from: an affiliate identifier, a country or a format anchor belongs to the site (20/08/2026)
- **BC break** — the four `epub_*` columns of `Book` are replaced by `BookLink` (table `book_link`), one row per platform, its kind a case of `BookLinkKind` carrying label, icon and address; Google Play audio, Spotify, Deezer, Apple Podcasts and YouTube come with it (19/08/2026)
- Added the `Book:Links` and `Book:Podcasts` components, `Book:Shops` now rendering whatever platforms the book carries rather than the four it knew (19/08/2026)
- Added `Book::$number`, the volume number a serie orders its books by and `BookSnippetBuilder` publishes instead of deducing it from the publication order (19/08/2026)
- Added `Book::$isbnAudio`, the audiobook having its own ISBN as the paper and digital editions do (19/08/2026)
- Added `BookSnippetBuilder` and the `book_json_ld()`/`serie_json_ld()` Twig functions, publishing the schema.org `Book` and `BookSeries` data of a book's and a serie's page (19/08/2026)
- `BookSitemapProvider` carries the `title` and `description` of the three indexes, of the books and of the series, which is what feeds the site's `llms.txt` (19/08/2026)
- Added `BookGuidedProjectProvider` and its three projects: create a serie, publish a book, compose its page (19/08/2026)
- Added `LinkableRouteProvider`, offering the three public indexes and each serie as a target for a SiteBundle menu item (19/08/2026)
- Added `BookBackupPathProvider`, declaring `public/medias/book` to ConfigBundle's backup, which held nothing of this bundle until now (19/08/2026)
- Added `BookBlockOwnerResolver`, and the three CRUD screens carry the block-move attributes on their `blocks` collection (19/08/2026)
- A published `Strip` renders the blocks composed on it, which its template never did (19/08/2026)
- Added `Media::MEDIA_DIRECTORY`, the upload root the six subclasses spelled out one by one (19/08/2026)
- Added `tests/Management/ManagementTargetsTest.php`, checking every target the management providers name (19/08/2026)
- Added `tests/Service/BookSnippetBuilderTest.php` (19/08/2026)
- The bundle requires `c975l/core-bundle` in `^1.0` instead of `c975l/ui-bundle` (19/08/2026) [BC-Break]
- The `book_books` and `book_serie_strips` blocks find their template again, the two files having been named `Books.twig` and `SeriesStrips.twig` (19/08/2026)
- `Book/Shops` no longer calls ShopBundle: it keeps the digital stores and drops the shop button, to be restored by overriding the template (19/08/2026) [BC-Break]
- `Book/Extracts` always uses UiBundle's slider instead of ShopBundle's on a published book (19/08/2026) [BC-Break]
- Removed `Book/Financement`, which pointed at CrowdfundingBundle's route (19/08/2026) [BC-Break]
- The `Shops` and `Extracts` cards take their title from the `book` translation domain and their anchors are `shops` and `extracts` (19/08/2026) [BC-Break]
- The digital store icons are served from `bundles/c975lbook/icons/` instead of the site's `images/` (19/08/2026)
- Added the `audit-deps` Composer script, run first by `qa` and by the CI workflow (19/08/2026)
- Composer's archive cache is carried from one run to the next, so a run whose resolved versions have not moved reaches the network for metadata alone - the archives are indexed on their own content, which owes nothing to a `composer.lock` this bundle still does not version (17/08/2026)
- The workflow runs on a push to main and on pull requests only, under a `concurrency` group that cancels a run the next push has superseded: dev carried the same commit, and the two twin runs resolved and downloaded the same packages at the same second (17/08/2026)
- `COMPOSER_TOKEN` is gone from the setup-php step: it never reached the archive downloads, which codeload.github.com serves through a cross-host redirect that drops the Authorization header, and no bundle of the Symfony ecosystem passes one either (17/08/2026)
- The workflow's `GITHUB_TOKEN` is pinned to `contents: read` rather than inheriting the repository's default write permissions: the checkout is the only step that reads it (17/08/2026)
- The templates state their page summary as `summarySocialNetwork`, the name both layouts read since UiBundle's was aligned on SiteBundle's (13/08/2026)
- Added the `qa` Composer script and its steps, which the CI workflow now calls (03/08/2026)
- Added `bin/ci.sh`, replaying the CI checks on dependencies freshly resolved from Packagist (03/08/2026)
- The two `strip/` templates extend the app's `layout.html.twig` instead of a `@c975LBook/layout.html.twig` that never existed (02/08/2026)
- `php` is now required in `>=8.4` instead of `>=8.0` (30/07/2026) [BC-Break]
- The `symfony/*` requirements are now constrained to `^8.0` instead of `*` (30/07/2026) [BC-Break]
- The `symfony/ux-*` requirements are now constrained to `^3.3` instead of `*` (30/07/2026)
- The third-party requirements left in `*` are now bounded on their installed version (30/07/2026)
- The `c975l/*` requirements are now bounded on their major (30/07/2026)
- `Book::$user`, `Media::$user`, `Serie::$user` and `Strip::$user` are now typed `c975L\ConfigBundle\Contract\UserInterface` instead of `App\Entity\User` (30/07/2026) [BC-Break]
- Added `phpunit.xml.dist` and the `test` script, the bundle's test having no harness to run it (30/07/2026)
- Added `.codacy.yaml` and `phpcs.xml.dist` (30/07/2026)
- Applied PSR-12 to the codebase (30/07/2026)
- Added `.php-cs-fixer.dist.php`, applying the Symfony coding standards (30/07/2026)
- Added `phpstan.dist.neon`, running the static analysis at level 5 (30/07/2026)
- Added `phpstan-baseline.neon`, freezing the errors that predate the analysis (30/07/2026)
- Added the `CI` GitHub Actions workflow, running PSR-12, the static analysis, the tests and the coverage upload (30/07/2026)
- The local Codacy CLI now runs `eslint@9.39.5` (30/07/2026)
- Added `BookSitemapProvider` (ConfigBundle's `SitemapProviderInterface`), so books/series/strips are declared in the site's `sitemap-index.xml` (26/07/2026)
- Expanded the explanatory text on the Book/Serie/Strip index and edit screens (22/07/2026)
- Removed the detail/view page on Book, Serie and Strip (22/07/2026)
- Added a Cancel action on every create/edit screen (22/07/2026)
- Index-page inline row actions (Edit/Delete/Detail) now show icon-only with the label as hover title, via ConfigBundle's `EasyAdminActionHelper::toIconOnly()` (16/07/2026)
- Added the Codacy grade badge to the README (30/07/2026)

## v1.2

- Added a "Regenerate sitemap" dashboard shortcut via ConfigBundle's new `ShortcutProviderInterface`, reusing `SitemapCreateCommand` (05/07/2026)
- Added help text to the Book, Serie and Strip CRUD indexes (13/07/2026)

## v1.1

- Added Export dropdown (SQL/CSV/JSON) to Book, Serie, and Strip CRUD controllers, using ConfigBundle's `TableExporter` (04/07/2026)
- Added SerieLogo component (28/06/2026)
- Corrected display of crud translations (28/06/2026)
- Added Strip entity + management (28/06/2026)
- Changed crud icons (28/06/2026)

## v1.0.1

- Corrected Book Informations component (27/06/2026)

## v1.0

- Added MenuProvider (27/06/2026)
- Added Readme (27/06/2026)
- Removed unused Listener (27/06/2026)
- Added translations (27/06/2026)
- Corrected templates (27/06/2026)

## v0.4

- Corrected wrong components calls and templates (25/06/2026)

## v0.3

- Suppressed specific data related to former use (25/06/2026)

## v0.2

- Renamed Tables and Folders (25/06/2026)

## v0.1

- Creation of bundle (25/06/2026)