# Changelog

## v2.5.2

The reader step points at the button opening the palette

- The `book-reader` step adding a block points at the collection's add button rather than at the collection, the palette the step after it names being opened by that click (03/09/2026)

## v2.5.1

The bundle's own javascript is run rather than read

- **This bundle's own javascript is now run rather than read**: 9 scenarios over `assets/js/reader.js`, where it had none (02/09/2026)
- New `tests/Assets/JsCase`, declaring where the assets are to UiBundle's `Testing\JsCase` harness (02/09/2026)
- `c975l/core-bundle` floor raised to `^1.21`, `chrome-php/chrome` added to `require-dev` (02/09/2026)
- The loop the reader must not close is put under load: the guard sends nobody back to the page they have just left, and is released on the next frame (02/09/2026)
- Cues read in time rather than in entry order, an untimed page never turned to, an album with no recording still turned by hand, and neither listener outliving the page Turbo caches (02/09/2026)
- **`#Serie_kind` is pointed at directly**, a two-value choice being a native select (02/09/2026)
- `#Book_previousVersion` is named through its row (`.form-group:has(...)`), its options crossing UiBundle's autocomplete threshold past ten living books (02/09/2026)
- The `book-composition` step scoping the blocks tab names the active pane, an id slugged from the translated label matching nothing outside French (02/09/2026)
- **New `book-reader` guided project** (order 6033): picking the kind in the palette, attaching the pages and the recording in one upload, then timing the pages cue by cue (02/09/2026)
- **A media row with no file, no name and no address is refused by the form** rather than written and handed to a template as a cover (02/09/2026)
- New `tests/Translation/NarrationCatalogueTest`, holding every declared narration to being shipped in both locales and every shipped one to still being declared (02/09/2026)
- The `c975l-book-display-pages` skill states the media rule next to the cover fallback (02/09/2026)
- **A media saved before a file is picked no longer answers 500**: `Media::$updatedAt` is stamped at construction, an upload moving the date forward (01/09/2026)
- **The export steps point at the button unfolding the menu**, the group carrying `action-export` through `addCssClass()` and the three formats being named by the step (01/09/2026)

## v2.5.0

An author is a row of the catalog, not a name retyped on each book

- **BC break** — `Book::$author`, `$illustrator`, `$authorWebsite`, `$illustratorWebsite` and the same four on `Serie` are replaced by two relations to the new `Contributor` (table `book_contributor`), one entity for both roles, the role being carried by the relation (31/08/2026) **Needs db migration** see [UPGRADE.md](UPGRADE.md)
- `Book::getAuthor()`/`getIllustrator()` and their `Effective` siblings return a `Contributor` where they returned a string (31/08/2026)
- `getEffectiveAuthorWebsite()`/`getEffectiveIllustratorWebsite()` stay, reading the address off the person (31/08/2026)
- `Contributor::__toString()` returns the name, so a template printing `{{ book.author }}` prints what it printed before (31/08/2026)
- New `ContributorMedia` (discriminator `contributor`), its portrait and its backdrop written under `medias/book/contributors/` (31/08/2026)
- New public pages `contributor_index` and `contributor_display`, behind the `book-route-contributors`/`book-route-contributor` entries — a site leaving them empty serves neither (31/08/2026)
- New `ContributorCrudController`, with the trash, the copy, the exports, the drag-and-drop ordering and the "Masqué" switch the three other screens carry (31/08/2026)
- A person is refused the trash while a book or a serie still credits them (31/08/2026)
- New `book_contributors` block kind, its silhouette added to `sass/block-thumbs.scss` (31/08/2026)
- `BookSitemapProvider` declares the index and each credited person (31/08/2026)
- `LinkableRouteProvider` offers the index and each person as menu targets (31/08/2026)
- New `ContributorExportProvider`/`ContributorImportProvider` (kind `book_contributor`) and `ContributorResolver` (31/08/2026)
- The book and serie archives keep the `author`/`authorWebsite`/`illustrator`/`illustratorWebsite` keys they always wrote, each name resolved back into its row (31/08/2026)
- `Book:Informations` links each name to their own page rather than to their site off ours, which their page carries instead (31/08/2026)
- `BookSnippetBuilder::person()` takes the entity, the `url` of the `Person` node now following whoever is credited rather than the row crediting them (31/08/2026)
- `BookDemoFixtureProvider` seeds the two people it credits before anything naming them (31/08/2026)
- The `book` and `site_config` catalogs gain their keys in the three locales (31/08/2026)
- New `contributor_path()`, `contributor_url()`, `contributor_edit_urls()` and `contributor_edit_url()` Twig functions (31/08/2026)
- `BookBlockEditUrlProvider` and `BookBlockOwnerResolver` take the person as a fourth block owner (31/08/2026)
- New `book-contributor-creation` guided project, opening the parcours the catalog is built in (31/08/2026)
- New `book-media-move`, `book-sorting`, `book-duplication` and `book-export` guided projects (31/08/2026)
- The `book-sorting` project names the two indexes the drag-and-drop order is laid on (31/08/2026)
- The README follows: the routes, the blocks, the trash, the switch, the sitemap, the graph and the archives (31/08/2026)
- The three shipped skills follow: the pages, the prefixes, the components and the eight export/import providers (31/08/2026)
- New `ContributorExportProviderTest` (31/08/2026)
- `MediaUploadMappingTest`, `MenuProviderTest` and `BookDemoFixtureProviderTest` take in the seventh media subclass, the fourth screen and the two people (31/08/2026)
- The showcase has no `book_contributors` tile until it is recaptured, the README's strip showing one per kind photographed there (31/08/2026)
- A renamed person or serie leaves a 301 behind again, `updateEntity()` chaining to the trait rather than taking its place (31/08/2026)
- A person's page leaves out the series trashed or set aside, their covers joined along the way (31/08/2026)
- The index of people leaves out someone whose only credits are books a newer version replaced (31/08/2026)
- The author and illustrator autocompletion of a book no longer offers someone set aside (31/08/2026)
- The "see them all" tile of the books and series listings reads its two labels from `book.*.xlf` rather than from the French written into the templates (31/08/2026)
- Its title is the new `label.books_more`/`label.series_more`, its button the existing short `label.books`/`label.series` (31/08/2026)
- **The button therefore reads "Livres"/"Séries" where it read "Tous nos livres"/"Toutes nos séries"** - a site keeping the sentence overrides those keys in its own `book.*.xlf` (31/08/2026)
- `Contributor::$slug` carries a unique index, the migration checking for two names slugified alike before it is laid (31/08/2026) **Needs db migration** see [UPGRADE.md](UPGRADE.md)
- An import falls back on a name only where the row found is a shell, a namesake keeping their own page (31/08/2026)
- `ContributorRepository::findOneByName()` leaves out someone in the trash, whom an import would otherwise credit on a book (31/08/2026)
- The "see them all" tile of the books, series and people listings answers its checkbox again, the value being brought down to a boolean (31/08/2026)
- A book crediting nobody no longer opens its hero line on a stray dash (31/08/2026)
- New `DisplayMoreCoercionTest` (31/08/2026)
- New `ContributorImportProviderTest`, taking in the namesake left alone (31/08/2026)
- New `ContributorTest`, `ContributorResolverTest` and `ContributorRepositoryTest` (31/08/2026)
- `BookPublicUrlGeneratorTestTrait` declares the two contributor routes and their prefixes, which left the tests reading them as a family served elsewhere (31/08/2026)
- `BookSitemapProviderTest`, `BookUrlExtensionTest`, `BookEditUrlExtensionTest` and `BookTrashManagerTest` take in the person (31/08/2026)
- The fixtures no longer carry the maintainer's own books: `La Compagnie des Ombres`, `Contes du Soir` and neutral character names stand where real titles, slugs and a real domain did - a public bundle names nobody's work but the reader's (01/09/2026)

## v2.4.3

The offers node is explained without naming another bundle

- `BookSnippetBuilder`'s note on the absent offers node drops its cross-repository reference (31/08/2026)

## v2.4.2

The README shows the block kinds as a strip of tiles

- New `.github/images/BookBlocks.png`, one tile per showcased block kind (31/08/2026)
- The README prints it under the blocks section, saying what the tiles do not show (31/08/2026)

## v2.4.1

A block kind is recognised by its silhouette in the picker

- New `sass/block-thumbs.scss`, one silhouette per block kind of this bundle, built into `public/css/block-thumbs.min.css` (31/08/2026)
- `StylesheetProvider` implements `BundleStylesheetManagementProviderInterface`, contributing that sheet to the back-office alone (31/08/2026)
- A site printing the silhouettes on a public page contributes the same file through its own provider (31/08/2026)
- The showcase cards carry a cover, read off the site's `book/<slug>` and `serie/<slug>` keys like the demo fixtures (31/08/2026)
- Failing a declared key, the generic pool is dealt by rank, so a rail never repeats a photograph (31/08/2026)
- New guided project `book-hidden`, which takes a book off the site without deleting it (31/08/2026)
- The trash parcours is renamed after the trash, the wording it shared with the switch that only sets a row aside (31/08/2026)
- The Vich upload is declared once on `Media::$file`, the six subclasses keeping only their `#[Vich\Uploadable]` (31/08/2026)
- New `phpmd.xml.dist`, kept to what the code chooses rather than what the framework imposes (31/08/2026)
- PHPStan ignores PHPMD's `@SuppressWarnings` marker, which it reads as a typed tag (31/08/2026)
- `.codacy.yaml` excludes the repository root's own `public/` and `tests/`, which the `**/` forms never covered (31/08/2026)
- `StripImportCommand` splits its import loop, its strip building, its source url and its media recognition (31/08/2026)
- The three import providers split their filling into what a row is, who made it and where it stands (31/08/2026)
- `StripImportCommand::execute()` reads its options through an `importContext()` that reports what it refuses (31/08/2026)
- New `BookCatalogExporter`, the connection and the two exporters the three catalog screens no longer each carry (31/08/2026)
- `BookCrudController::configureFields()` splits into one method per tab (31/08/2026)
- New `MediaUploadMappingTest`: the six subclasses upload through the field declared on `Media` (31/08/2026)
- `StylesheetProviderTest` checks every pickable kind has its silhouette, the kinds read off `services.yaml` (31/08/2026)
- `GalleryShowcaseProviderTest` covers the cards' covers, the declared key winning over the pool (31/08/2026)
- `BookBlockEditUrlProviderTest` checks the three families still name a CRUD controller (31/08/2026)
- The README documents the silhouettes sheet and the showcase covers (31/08/2026)
- The `c975l-book-display-pages` and `c975l-book-lifecycle` skills follow (31/08/2026)

## v2.4.0

A book, a serie or a planche is set aside without being deleted

- New `Entity\Trait\HideableTrait`: `Serie`, `Book` and `Strip` carry a `hidden` column, the switch that takes a row off the site without deleting it (30/08/2026)
- A row set aside leaves every public read at once - listings, search, numbered urls and short links, the previous/next bar of a planche, the character chips, the linkable routes and the sitemap at its next run (30/08/2026)
- A serie whose every book or planche is set aside stops heading a section with nothing under it: the join filters too (30/08/2026)
- The public page of a row set aside answers 404, not the 410 of the trash: nothing was taken away (30/08/2026)
- The `Masqué` switch is offered on the three index screens as well as on their forms (30/08/2026)
- Masking a serie is refused while it still holds a book or a planche that is shown, the same guard the trash carries (30/08/2026)
- Masking a serie from its index switch answers an error, which is what puts the switch back (30/08/2026)
- A book or a planche of a serie set aside leaves the public reads with it (30/08/2026)
- Its trail names the serie without linking to a page answering 404 (30/08/2026)
- The block announcing the books to come filters `hidden` like every other public read (30/08/2026)
- New `Serie::holdsVisibleContent()`, which counts neither what is already set aside nor what is in the trash (30/08/2026)
- `hidden` travels through the three export/import providers; an archive written before the column existed reads as shown (30/08/2026)
- `label.hidden` and `label.hidden-help` are added to the three locales (30/08/2026)
- The README documents setting a row aside, and the `c975l-book-lifecycle` skill tells it apart from the trash (30/08/2026)
- `BookRepositoryTest`, `SerieRepositoryTest` and `StripRepositoryTest` cover the filtering, the joins included (30/08/2026)
- A serie takes the pictures the site declares for it, keyed `serie/<slug>` (30/08/2026)
- The three are read in order as its cover, the emblem printed above its title and the picture its page opens on (30/08/2026)
- **Fixed** - the serie's logo was drawn distorted, the hero's flex column stretching the `<img>` to its full width: `align-self: start` and `object-fit: contain` (30/08/2026)
- The logo takes a `--book-hero-logo-height`, 96px by default - a `height`, which the tag's `height` attribute obeys where a `max-height` did not (30/08/2026)
- The breadcrumb takes a `--book-breadcrumb-margin-block-start`, 1.5rem by default (30/08/2026)
- The scaffolded `book.css` offers both, which only the compiled stylesheet named (30/08/2026)
- The Books, Series and Strips screens carry their own labels, EasyAdmin falling back on the class name without them (30/08/2026)
- New `BookSampleCatalog`, the made-up catalog held once: two series of two books, three out and one still to come (28/08/2026)
- New `BookDemoFixtureProvider` (`c975L\UiBundle\Contract\DemoFixtureProviderInterface`): the series first, then the books that name them (28/08/2026)
- A book takes the covers the site declares for it, keyed `book/<slug>` (see CoreBundle's `keyed_images`) (28/08/2026)
- Failing that, one of the generic pool, read off the slug so a book keeps the same picture whatever is loaded beside it (28/08/2026)
- A catalog with no picture to show is still a catalog: the card falls back on the bundle's own `no-cover.webp` (28/08/2026)
- The book still to come carries no date at all, a written-down future one turning into a released book the year it came round (28/08/2026)
- `GalleryShowcaseProvider` reads the same catalog rather than its own "Livre exemple 1": one dataset, two readings (28/08/2026)
- The `label.book_sample_*` keys are added to the three locales, so a demo seeded in Spanish reads as a Spanish catalog (28/08/2026)
- Requires `c975l/core-bundle` `^1.19` for `DemoFixtureProviderInterface` and `PlaceholderMediaRegistry::getImagesFor()` (28/08/2026)
- Added `tests/Service/BookDemoFixtureProviderTest.php` (28/08/2026)
- The README documents seeding a demo catalog and the `book/<slug>` / `serie/<slug>` keys it reads (30/08/2026)
- The `c975l-book-lifecycle` skill covers the demo seed, the two consumers of `BookSampleCatalog` included (30/08/2026)

## v2.3.1

Logo modified

## v2.3.0

A reply is shared as the very card the page shows

- A planche read for its words (`book-strip-card` on `text`) shows its card alone, its medias no longer printed under the words they repeat (`strip/display.html.twig`) (25/08/2026)
- Those medias stay the page's `og:image` and its structured data's `image` (25/08/2026)
- Such a card takes back the square shape it has in a listing, `--book-strip-card-full-size` (25/08/2026)
- Added the `strip_card` route and `strip/card.html.twig`, the card alone in a square frame for a headless browser to photograph - `noindex`, gated as `strip_display` is (25/08/2026)
- Added `.strip-capture` and `--book-strip-capture-padding`, the frame's own ground and edge (25/08/2026)
- Added `strip:card`, which hands each capture to the media its planche already carries, Vich naming the new file and deleting the one it replaces (25/08/2026)
- Added `StripCardCommandTest` and `StripDisplayCompositionTest` (25/08/2026)
- `c975l/core-bundle` is required from `^1.17.4` and no longer from `^1.18`, a version that was never released: composer fell back to v2.1.2, whose `BookService` still autowires KnpPaginator, on a site that had already dropped that bundle (25/08/2026)

## v2.2.2

A book, a serie and a strip serve structured data a parser reads

- The `book`, `serie` and `strip` display templates print their JSON-LD with `raw`, a `{% set %}` dropping the function's `is_safe` marking (25/08/2026)
- Added `StructuredDataMarkupTest`, reading that every JSON-LD block of the templates is printed unescaped (25/08/2026)

## v2.2.1

A planche listing passes its boolean props in their expression form

- `serie/display.html.twig` calls `Strip:Cards` with `:infinite="true"` and `:rating="true"`, the string form reaching Twig as a true string (25/08/2026)

## v2.2.0

The catalog, the series and the planches grow as they are read

- The three index pages grow on scroll instead of turning pages, UiBundle's `infiniteScroll` controller fetching the page their own "next" link points to (25/08/2026)
- `Book:Books` and `Serie:Series` take an `infinite` prop marking their list for that controller (25/08/2026)
- A listing's counter reads what is on screen, the controller writing that figure back after each append (25/08/2026)
- **BC break** — `BookService`, `SerieService` and `StripService` return UiBundle's `Pagination` instead of Knp's `PaginationInterface` (25/08/2026)
- **BC break** — `knplabs/knp-paginator-bundle` leaves the bundle's dependencies (25/08/2026)
- Requires `c975l/core-bundle` ^1.18, which is where `Paginator` lives (25/08/2026)

## v2.1.2

The bundle's tooling is CoreBundle's

- Added `eslint.config.mjs`, `.stylelintrc.json` and `.markdownlint.json`, the lint configurations CoreBundle carries (25/08/2026)
- Aligned `.codacy.yaml` on CoreBundle's, `**/tests/**` and `**/*.tpl.php` now excluded (25/08/2026)
- `UPGRADE.md` opens its version section on `##` (25/08/2026)
- `skills/c975l-book-lifecycle/SKILL.md` labels its diagram fence `text` (25/08/2026)
- `.gitattributes` is CoreBundle's, the lint and tool configurations, `/bin`, `/tests` and `/.github` leaving the dist archive (25/08/2026)
- Rector caches in `.rector.cache`, inside the repository (25/08/2026)
- `composer rector` drops `--clear-cache` (25/08/2026)
- `bin/ci.sh` is CoreBundle's, leaving that cache out of the copy (25/08/2026)
- `rector.php` lists `scaffold/` among its paths (25/08/2026)

## v2.1.1

A planche of the block showcase shows its drawing

- `GalleryShowcaseProvider` attaches a placeholder image to each sample planche, the three cards being empty until now (25/08/2026)
- The photos come from `PlaceholderMediaRegistry`, an app declaring none getting the bare cards it got before (25/08/2026)

## v2.1.0

A book no longer holds a trailer or a filmed episode

- The video address field no longer carries a `placeholder` (`BookOwnedMediaType`) (24/08/2026)
- The books search reads its label, its placeholder and its empty answer off the `book` catalog (`BookSearch`) (24/08/2026)
- **BC break** — The `trailer` and `podcast` kinds leave `BookMediaKind`, their collections leave the back office, and the cards no longer print them (24/08/2026)
- **BC break** — Removed `BookSectionsExtension::CARD_KINDS`, the `book_card_kinds()` Twig function, and `Book::getTrailers()` and `getPodcasts()` with their accessors (24/08/2026)
- **BC break** — `label.media_trailer`, `label.media_podcast`, `label.trailer_media-help` and `label.podcast_media-help` leave the `book` domain (24/08/2026)
- A book's reviews are held in the very cache its blocks are held in (`ui_reviews_section()`) (24/08/2026)
- Added `UPGRADE.md`, naming the breaking changes and the steps a consuming app takes (24/08/2026)
- A book's card and a planche's are drawn as `<article>`, a book's section as the `<section>` it is (24/08/2026)
- A book's card takes the `compact` variant of the collection block drawing it (`Book:Card`, `BookItem`) (24/08/2026)
- A book's summary is printed as the html the editor wrote (`Book:Card`) (24/08/2026)
- A component's boolean prop documented in its string form fails the suite (`BooleanPropDocblockTest`) (24/08/2026)
- A platform player no longer collapses to a 300x150 thumbnail when it replaces its consent screen (24/08/2026)
- A platform player is no longer bound by the media height ceiling, taking that width at 16/9 (24/08/2026)

## v2.0.0

A book owns its files and publishes its own versions

- Publishing a new version of a book already replaced once chains the previous version behind the twin instead of sending it back to the catalog (23/08/2026)
- Duplicating a book no longer copies its versions, the copy leaving the source out of the catalog (`BookDuplicator`) (23/08/2026)
- `StripRepository::findOneByNumber()` skips the trash and the unpublished, `BookRepository::findOneByNumber()` the trash (23/08/2026)
- A book's version fields offer neither the trash nor the book being edited (`BookCrudController`) (23/08/2026)
- `BookCustomizationProviderInterface::getSections()` no longer announces the `edition` and `serie` keys, which the bundle does not print (23/08/2026)
- A book's three file collections share one form (`BookOwnedMediaType`), each declaring only its entity, the word naming its file and whether an address may stand in for an upload (23/08/2026)
- The guided step showing where to add a block points at the collection again (`[data-ui-sort-group="block"]`), the marker it highlighted having left UiBundle (23/08/2026)
- `BookSectionsExtension` no longer receives `BookEditionExtension`, which it did not read (23/08/2026)
- A book's page shows the reviews readers left on it, and the link to leave one (23/08/2026)
- Added `BookFavoriteItemProvider`, which is what names a book on the review page and on a wishlist (23/08/2026)
- Added `BookRepository::findPublishedByIds()` (23/08/2026)
- `BookTrashManager` drops a book's reviews along with its ratings when it is removed for good (23/08/2026)
- The six public pages no longer set an hour of browser cache of their own (`max-age=3600, private`, Symfony marking it private for want of a `public` or an `s-maxage`): what a page holds is cached per fragment and emptied when it changes, where the header froze it in the visitor's own browser whatever happened server-side (23/08/2026)
- Requires `c975l/core-bundle` ^1.16, which is where the reviews live (23/08/2026)
- **BC break** — `BookMedia` and `BookLink` no longer name an edition, the `edition_id` columns being dropped — a consuming app has to migrate its own schema (23/08/2026)
- **BC break** — Removed `BookEdition::$published`, `$medias` and `$links` with their accessors and `isReleased()` (23/08/2026)
- Added the "Publish a new version" action and `Service\BookVersionPublisher` (23/08/2026)
- Added `Controller\Management\BookMediaMoveController`, moving a file from one collection of a book to another (23/08/2026)
- Added `Listener\BookVideoNoCookieListener`, rewriting a pasted video address to its platform's canonical form on save (23/08/2026)
- Added `Service\BookCollectionSourceProvider` and `Book:Card`, the book a UiBundle collection prints (23/08/2026)
- Added `skills/c975l-book-display-pages`, `skills/c975l-book-customization` and `skills/c975l-book-lifecycle`, shipped in the package and guarded by `tests/SkillsTest.php` (23/08/2026)
- A book and a strip answer to their **number** wherever their slug is expected, `/livre/3` and `/livre/003-le-tracteur` both leading 301 to `/livre/le-tracteur`, the slug being tried first (22/08/2026)
- Added the **short link**, one letter and the number (`/b3`, `/s3`), the letter being the site's own (`book-route-book-shortcut`, `book-route-strip-shortcut`) and no such address served once it is emptied (22/08/2026)
- A number always leads to the row the catalog still lists, `BookRepository::findOneByNumber()` setting aside the replaced versions and fixing the order (22/08/2026)
- `BookServiceInterface` and `StripServiceInterface` take `findOneBySlug()` and `findOneByNumber()`, the book and strip pages resolving their slug themselves rather than through the router's converter (22/08/2026)
- **BC break** — A serie is read under the index listing it, `/series/{slug}` for the ones telling books and `/strips/{slug}` for the ones telling strips: `book-route-serie` is gone, `strip_serie_display` joins `serie_display` (`BookPublicUrlResolver::serieRoute()`, `serie_path()`, `serie_url()`), and a serie asked under the other segment answers 301 (22/08/2026)
- The strips index (`book-route-strips`) lists the strip **series** rather than the strips themselves, the two indexes sharing the series on `Serie::kind` (`SerieKind`, `Serie::isStripSerie()` for an undeclared one) (22/08/2026)
- **BC break** — The `strip_character` route is gone, along with `StripServiceInterface::findAllPaginated()` and `findAllByCharacterPaginated()`; `label.strips_list` gives way to `label.strips_series` and `label.strips_series_list` (22/08/2026)
- The breadcrumb climbs to the index of the read serie's own family, and a strip's exit buttons (`Strip:Random`, the strip to come) lead to its serie first (22/08/2026)
- A book replaced by a newer version leaves its serie's page and the count it shows (`SerieRepository`, joins on `b.newerVersion IS NULL`), the search answering the replaced versions again (22/08/2026)
- A text card starts from a floor height (`--book-strip-card-min-height`) rather than an aspect ratio, growing with what it carries (21/08/2026)
- A translated book's page declares its language group (`book_alternates()`, `<link rel="alternate" hreflang>`), in absolute urls built on `site-url` like the canonical one (21/08/2026)
- Every page of the bundle holds to the measure the site declares for its sections (`.book-page`, `--section-wrap-max-width`) (21/08/2026)
- A strip card is three per row in the box holding it (`.cards .strip-card`, `calc((100% - 2 * var(--cards-gap)) / 3)`), falling to two then one under `--book-strip-card-min-width` (21/08/2026)
- On a strip page the card is set to the drawing's measure (`--book-strip-media-max-width`) (21/08/2026)
- A serie filters its strips on who speaks through a query rather than a page apart (`/strips/x?character=maman`), `Strip:Characters` reading the names without a link where neither list is served (21/08/2026)
- A card's dialogue is read whole, the square being a floor and no longer a frame, and the fade goes with `--book-strip-card-text-fade` (21/08/2026)
- The three paths leading to the list of every strip are conditioned on the site serving it (`Strip:Random`, the strip to come, the breadcrumb) (21/08/2026)
- A card's dialogue takes the height the square leaves it and fades where it overflows (`--book-strip-card-text-fade`, written in `currentColor`) rather than being cut at a fixed line count (21/08/2026)
- `--book-strip-card-text-space` goes from 1.5em to 0.75em (21/08/2026)
- The list filtered on a character carries the breadcrumb, which leads back to the whole list (21/08/2026)
- Under a serie's search field, the badges of who speaks in it (`StripRepository::findCharactersBySerie()`), `Strip:Characters` taking a heading level (21/08/2026)
- The "who speaks" field is split by `Strip::splitCharacters()`, static, so a list names a whole serie's characters without loading its strips (21/08/2026)
- A serie's strips are read from the most recent to the oldest, as the list of every strip is (21/08/2026)
- A serie's page no longer wraps what it lists in a card: a title, the field searching it, then the cards themselves (21/08/2026)
- The two live searches hold to a serie when the page is one (`serieId`, `search($query, ?int $serieId)` on strips as on books, covered by `StripRepositoryTest`) (21/08/2026)
- `Book:Search` takes a `serie` and a translation key (`label.book_search`), its title and label having been written in French in the template (21/08/2026)
- A serie's hero opens on its background image like a book's (`Serie::getBackgrounds()`, one "Background image" field), printed as an `<img>` and its veil rather than a `background-image` a nonced `style-src` refuses (21/08/2026)
- A serie's page lists every one of its strips, the list growing on scroll (`StripService::findAllBySeriePaginated()`, 24 a page) (21/08/2026)
- A serie's summary reads as a book's, under the hero, large and without the word "Résumé" (21/08/2026)
- No more anchor summary on a serie's page, the hero's buttons opening the two rubrics it may hold (21/08/2026)
- The breadcrumb is one component for both pages (`c975LBook:Breadcrumb`), stopping on a serie and linking it on a strip (21/08/2026)
- Every book card carries its pencil (`book_edit_url()`, `editable` prop of `Book:Book` and `Book:Books`) (21/08/2026)
- `Strip:Cards` takes an `id`, a page already naming the block carrying the list handing it an empty one (21/08/2026)
- A serie removed for good takes its ratings with it, as a book and a strip do (21/08/2026)
- Removed `phpstan-baseline.neon`, its 3 entries all naming the `$blocks` false positive, now a motivated exception in `phpstan.dist.neon` (21/08/2026)
- Removed the always-true `instanceof BookMedia` of `detachFromEditions()`, `Book::getMedias()` returning `BookMedia` alone (21/08/2026)
- A strip's rating reads centered in its hero, the flex row being untouched by text centering, and stays left-aligned beside a cover (21/08/2026)
- A strip's navigation is read on the strip itself: `Strip:Breadcrumb` opens it and its neighbours are two arrows laid on `.strip-viewer` (`Strip:Previous`, `Strip:Next`), fading at rest where a pointer can call them back (21/08/2026)
- Removed `Strip:Navigation` and the `--book-bar-*` tokens, the bar stuck to the bottom of the screen being replaced by the above (21/08/2026) [BC-Break]
- Every card of the strips list carries its pencil (`strip_edit_url()`, `editable`), a card rendered in a block carrying none, its HTML being cached and shared (21/08/2026)
- The rating sits at the foot of the card whatever the length of the dialogue above it (21/08/2026)
- A strip search says it is searching, `<twig:c975LUi:Search:Busy/>` replacing the empty line held under the field (21/08/2026)
- The button leading to a strip's source has its own key, `label.source_url_link`, `label.source_url` staying with the CRUD alone (21/08/2026)
- A list filtered on a character carries the way back, the selected badge leading to the current page (21/08/2026)
- A card's dialogue is spaced whatever writes it (`.strip-card__text > * + *`), the rule naming `p` where the Trix editor separates its lines with a `<div>` (21/08/2026)
- A strip is rated with the icon the site picks for it (new `book-strip-rating-icon` setting), falling back to the site's own `ui-rating-icon` when empty (21/08/2026)
- A strip's title reads centered, the two-column layout opening only on a hero carrying a cover (`.book-hero:has(.book-hero__cover, .book-hero__cover-flip)`) (21/08/2026)
- A strip's and a serie's page read on the whole width, the two columns opening only on a page carrying an anchor summary (`.book-page:has(> .book-page__toc)`) (21/08/2026)
- On its own page a strip's card is exactly the one of the list, save that it summarizes nothing: the dialogue is read whole and the card grows under the square (21/08/2026)
- The navigation bar names the strip it leads to, cut at `--book-bar-label-max-width`, and the serie loops, a serie of one strip keeping its two empty places (21/08/2026)
- A strip's page shows the very card of the list, whole and leading nowhere (`strip-card--full`), its order following the `book-strip-card` setting; `Strip:Summary` is no longer called (21/08/2026)
- Readers' ratings sit on a strip and on a serie too, under the line saying what the page tells, on the same `book-rating` setting, and a row removed for good takes its votes with it (`BookTrashManager`) (21/08/2026)
- A strip card is clicked whole, the title's anchor being stretched over its surface rather than a second link added, and lifts by `--book-strip-card-lift` on hover as on focus (21/08/2026)
- A card showing words rather than a drawing takes a square shape (`--book-strip-card-ratio`), opened by the mark the site names (`book-strip-card-icon`) and holding its summary at the center (`--book-strip-card-text-size`, `--book-strip-card-text-gap`, `--book-strip-card-text-lines`) (21/08/2026)
- The date reads at the end of the title bar, on its line, handed to UiBundle's card as its `titleAside` rather than laid over it (21/08/2026)
- On a card the mark opens the strip at the top and the dialogue starts under it at a fixed measure (`--book-strip-card-text-space`), a step larger and left-aligned, whoever speaks reading in the title font (21/08/2026)
- Two strips published the same day are listed from the most recent, the three descending lists breaking the tie on the id (21/08/2026)
- A card's mark is rendered as an image rather than an icon, a theme repainting an icon (`--icon-filter`) and `img.icon` capping it at 24px (21/08/2026)
- A strip's card is UiBundle's card (`c975LUi:Card:Card`) rather than an `<article>` of its own, and what it carries is the site's choice (new `book-strip-card` setting: `image`, `text` or `both`) (21/08/2026)
- The strips list grows on scroll instead of turning pages (UiBundle's `infiniteScroll` controller), "Voir plus" staying the plain link to the next page without JavaScript (21/08/2026)
- A strip search reads its summary too (`StripRepository::search()`, covered by `StripRepositoryTest`) (21/08/2026)
- The search's three hard-coded texts move to keys of the `book` domain (`label.strip_search`, `label.search_placeholder`, `label.no_result`, `label.load_more`) (21/08/2026)
- A strip stops growing with the screen (`--book-strip-media-max-width`, 88vw then 640px, `--book-strip-card-image-width` on the card) (21/08/2026)
- On a book's page the serie is named and no longer painted nor clickable, its logo and its link leaving the hero, the serie's own page keeping them (`Serie:Hero`) (20/08/2026)
- The hero's two buttons read on the section's key and write its real anchor, their label being the one the site gave the section (20/08/2026)
- The Google Play affiliate identifier is configured once in the sensitive `book-gplay-affiliate` setting and added to the `play.google.com` addresses on display (`book_link_url()`) (21/08/2026)
- A book's hero prints under its title the fragment the site declares for the `hero` key (`book_section_template()`), the slot it opened being out of reach (20/08/2026)
- A site declares a rubric the bundle has not: a key of none of its own carrying the `template` printing it opens a section in its own right, at the rank it is declared and edited by the pencil of the field it names (`getSections()`, new `form` and `field` keys) (20/08/2026)
- The checked rows of an index export to a re-importable zip through the "Export the selection" batch action (20/08/2026)
- The whole catalog leaves through the "Export the sync (all)" tile and comes back through the import button (`SerieExportProvider`, `BookExportProvider`, `StripExportProvider` and their `*ImportProvider`) (20/08/2026)
- Duplicating a serie, a book or a strip checks a CSRF token, as restoring and removing for good do (20/08/2026)
- The links health check skips the trashed books, `BookRepository::findAll()` setting the deleted rows aside (20/08/2026)
- A hosted video no longer writes a name on its media row, the column being unique and the same video being laid on two books (20/08/2026)
- A book's video is asked in 720×405 rather than as a 320 thumbnail, which also bounds the consent cache preceding it (20/08/2026)
- The "Listen" card holds everything the book is listened by: the recording, the files taken away, then the audio stores and the podcast apps (`Book:Player` removed) (20/08/2026)
- A version carrying its recording alone no longer opens a section, which stayed titled and empty (20/08/2026)
- An `.ogg` file has a type at last, the recording falling back to `application/octet-stream` until now (`Entity\Media`) (20/08/2026)
- The "Informations" rubric reads in a shadowed frame like the summary, the new `framed` form of `book/_section.html.twig` (20/08/2026)
- A rubric's name reads a step larger, framed or not, on the new `--book-section-title-size` (20/08/2026)
- The confetti open on a book's page behind the new `book-confetti` setting, unchecked by default and never fired for a visitor asking for fewer animations (20/08/2026)
- The "Videos" part of a book's form moves under the versions (20/08/2026)
- A card holding two link groups prints its buttons in the book's order rather than group after group (`book_links_of()`) (20/08/2026)
- The hero of a book page carries the ratings its readers give it, under the author and the date, behind the new `book-rating` setting — on by default, the site that would rather its pages did not ask for a vote turning it off (`<twig:c975LUi:Rating:Rating ownerType="book">`, see UiBundle) (20/08/2026)
- `BookTrashManager::deletePermanently()` removes the ratings of the row it deletes, which hang off "book" + id and are cascaded by nothing — never on the way to the trash, a restored book having to find its notes where it left them (20/08/2026)
- Ausha joins the listening platforms (`podcast_ausha`), filed in the "Listen" card like the others (20/08/2026)
- Deleting a serie, a book or a strip moves it to the trash, where it is restored or removed for good — its page answering 410 while it sits there (`Service\BookTrashManager`, `Contract\TrashableInterface`) (20/08/2026)
- A row removed for good leaves a `gone` Redirect on its url, and a renamed one a 301 from its old path — a serie is refused the trash while it still holds a book or a strip (20/08/2026)
- A book carries its own extracts and its own flipbook, each on its own field of the "Media" tab after the hero's backdrop (`Book::getExtracts()`, `getFlipbooks()`, `BookFlipbookType` for the mp4) — `Book:Extracts` reads that collection rather than "every file no edition claims", and the flipbook is printed under the hero outside any card (20/08/2026)
- The vertical step of a book page is the site's own (`--section-space`), and the summary is set larger and closer to the hero (20/08/2026)
- Every platform of a links card is a tile of the same size on a translucent white plate with rounded corners, label included (`--book-link-tile-*`) (20/08/2026)
- A site says which rows its catalog page lists and under what name (`BookCatalogProviderInterface`, `BookCatalogRegistry`, tag `book.catalog_provider`) — "the books whose own date has passed" is not what a catalog published in editions means by out, and the page's title was written in French in the template (`book_catalog_title()`) (20/08/2026)
- A book, a serie or a strip is duplicated from its index or from its own screen, with its files, its versions, its platforms and its blocks — a serie's books and strips stay with the original (`Service\BookDuplicator`) (20/08/2026)
- The extracts come first, and no longer hold the three images a book carries as a whole — its cover, its fourth of cover and the backdrop its page opens on, each uploaded on a field of its own and shown by the hero, never as a page of the book (20/08/2026)
- A slider in a media section keeps its images' own proportions (`object-fit: contain`), the height it gives itself having cropped a page drawn in portrait to a box that is not (20/08/2026)
- The summary is centered under the hero and set a step above the body copy, on the page's whole width rather than in the column of the sections (`--book-lead-size`, `--book-lead-pull`) (20/08/2026)
- The summary is no longer a section: `Book:Resume` prints it under the hero and outside the grid, with no title and no anchor — a summary is not a destination anyone jumps to, and the word "Résumé" was said twice, on the page and in its summary of anchors (20/08/2026)
- A book's two covers and the backdrop of its page are uploaded on three fields of their own (`Book::getCovers()/getBackCovers()/getBackgrounds()`, `Form\BookCoverType`, "Média" tab): the field a file is dropped on is what says which of the three it is, so a site never has to name any of them in its own vocabulary — and a site already storing a still of its own under one of its editions is left alone, a version's file carrying the edition it was published in where a cover carries none (20/08/2026)
- Added `book_media(book, kind)`, and `book_cover()` now reads the cover uploaded on that field before falling back to the first image of the catalog — which was the first image *stored*, so uploading a recording's thumbnail changed what stood for the book (20/08/2026)
- `Book:Hero` takes `coverBack` and turns the cover over on a click, front and back, reusing UiBundle's flip card — its classes and its `flipCard` controller, not its component, which reads `Media` entities where a hero is handed paths (20/08/2026)
- `book/display.html.twig` hands the hero the book's own backdrop, which had no way of reaching it since the page became the bundle's (20/08/2026)
- What a section carries decides its form, the card no longer being the wrapper of everything: text reads at the measure of a paragraph, a media is capped in height rather than taking its natural size, and a row of platforms holds one line (`book-section--text/--media/--links`, `--book-media-max-height`, `--book-text-measure`) — a trailer filmed on a phone printed at 1080x1920, two full screens for one file (20/08/2026)
- From 1200px the short sections pair up in a second column (`.book-page__grid`), the media ones keeping the whole width — the grid holds the sections alone, the sticky player staying a child of the flowing column (20/08/2026)
- The videos a book holds and the platforms it is watched on share one card, the "video" link group having had a name in the vocabulary and been printed by nothing (20/08/2026)
- `Book:Hero` reads `effectiveAuthor`/`effectiveIllustrator`, and names the illustrator only when they are not the author — the same name was printed twice (20/08/2026)
- The book, serie and strip summaries are edited with `TrixEditorType`, Donovan's rephrase button coming with it (20/08/2026)
- A book raising its money says so: `crowdfunding` is a section of its own (`Book:Crowdfunding`), the end date carried with it and the button gone once the campaign is over — the two columns were never rendered anywhere (20/08/2026)
- `Book:Video` reads the media it is given: `asset()` on the path, the media's own mimeType, a YouTube identifier played behind the consent gate, and the video's own title naming it — a file was handed to the player as a raw name, forced to "video/mp4", and a `youtubeUrl` came out as a broken `<video>` (20/08/2026)
- Added `book_translations()` and `book_language_label()` (`BookTranslationExtension`), `Book:Hero` offering every other language the book is written in, read from whichever end of the family the visitor arrived at — `Book::getTranslation()` walks the children alone, so a translation offered no way back to its original (20/08/2026)
- A numbered book outside any serie prints its number, which used to go with the serie's own line (20/08/2026)
- Added `Service\ScriptProvider` and `Management\ImportmapProvider`, the reader's controller being loaded on its own (20/08/2026)
- **BC break** — `BookCustomizationProviderInterface::getSections()`: a site declares the order, the anchor, the name, the icon, the entrance effect and the fragment it adds inside each section of a book's page, and follows the bundle's page instead of drawing one of its own (20/08/2026)
- `book/display.html.twig` loops over `book_sections()`, whose entries now carry `key`, `anchor`, `label` and, where they apply, `icon`, `animation`, `template` and the edition itself (20/08/2026)
- Every section is printed by `templates/book/_section.html.twig`, the one card writing the anchor, the name, the icon, the entrance effect and the editor's pencil - its contents handed over as an already-rendered string, a block declared around a component's slot never reaching the inside of it (20/08/2026)
- A section's entrance effect is a block's own (`.block-animation.scroll` + `data-animation`, read by `animate-scroll.js`), the controller being mounted only when a section asks for one (20/08/2026)
- On a book, serie or planche page, each section hovers a pencil leading to the very field it is written in, for whoever the screen lets in (`book_edit_urls()`, `serie_edit_urls()`, `strip_edit_urls()` - `BookEditUrlExtension`) (20/08/2026)
- The display components take an `editUrl`, the pencil sitting on the card each of them prints (20/08/2026)
- Added `BookBlockEditUrlProvider` and `findByBlockIds()` on the three repositories, so the blocks composed at the foot of a page hover that same pencil (20/08/2026)
- The book, serie and strip screens sit behind `site-role-editor` rather than `site-role-admin`, composing a catalog being an editor's job — only the raw table exports stay stricter; the guided project follows (20/08/2026)
- Each `BookEdition` holding files opens a section of its own on the book page — `Book:Editions`/`Book:Edition`, anchored `edition-<kind>` and titled by the site's own vocabulary (20/08/2026)
- **BC break** — `Book:Extracts` only shows the files no edition claims, an edition's own pages being shown under that edition's name (20/08/2026)
- Added `Book:Player`, the recording resting against the bottom of the screen as the last child of the sections column (20/08/2026)
- Added `book_path()` (`BookUrlExtension`), `Book:Hero` and `Book:Serie` naming a serie without linking it where the series are not served — `path()` throws on a route whose configured prefix is empty, which took a book page down with it (20/08/2026)
- `Book:Hero` takes a `cover`, a site handing over the file standing for the book when "the first image" is not it (20/08/2026)
- Added `book_loose_medias()`, `book_audio_medias()` and `book_cover()` (`BookSectionsExtension`), the hero printing the first image the book holds rather than its first file (20/08/2026)
- `Book:Player` reads `<twig:c975LUi:Audio:Audio sticky="true">`, which `c975l/core-bundle` ships from its next tag — the constraint in `composer.json` has to follow it (20/08/2026)
- An image uploaded in an edition, video, marketing or press row shows its thumbnail, and the file just picked previews before saving (`MediaFileFieldTrait`) (20/08/2026)
- **BC break** — `BookLink::$kind` is a plain string, the platforms being the vocabulary a site declares (`BookCustomizationProviderInterface::getLinkKinds()`); `BookLinkKind` stands in as the default catalog, the way `BookEditionKind` does for the editions (20/08/2026)
- **BC break** — Removed `BookLink::getLabel()`/`getIcon()`, `Book::getLinksOf()` and `BookLinkKind::choices()`; a template reads `book_link_label()`, `book_link_icon()` and `book_links_of()` (`BookLinkExtension`), and `Book::getLink()` takes a string (20/08/2026)
- `BookCustomizationRegistry` answers a platform's label, icon and group, and the links of a group — a kind the vocabulary does not hold prints as it is stored rather than failing (20/08/2026)
- The addresses a book's structured data gives per edition are read off the edition's own links, no longer guessed from the group they fall in (20/08/2026)
- **BC break** — A `BookEdition` holds its files and its platforms, and is edited in the accordion of the book's form (`BookEditionType`) (20/08/2026)
- **BC break** — `BookMediaType`/`BookLinkType` no longer take a `book` option nor offer an `edition` choice — the panel editing them already names the edition (20/08/2026)
- `BookEdition` owns its `medias` and its `links`, and hands them over to the book when it is attached to one (the path a file is stored under is built on the book's slug) (20/08/2026)
- Deleting an edition deletes its files and its links, the `edition` foreign keys moving from `ON DELETE SET NULL` to `ON DELETE CASCADE` — a consuming app has to migrate its own schema (20/08/2026)
- Added `management/book_crud_form_theme.html.twig`, naming each edition in the header of the accordion entry editing it (20/08/2026)
- The book creation tour points at the editions and at the button adding one, its two media steps dropped along with `label.guided_step_book_creation_media_tab` and `label.guided_step_book_creation_medias` (20/08/2026)
- Removed `label.media_edition`, `label.link_edition`, `label.edition_whole_book` and `label.links-help`; added `label.edition`, `label.position`, `label.edition_medias-help` and `label.edition_links-help` (20/08/2026)
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

## v1.1

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
