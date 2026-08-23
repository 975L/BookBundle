<?php

namespace c975L\BookBundle\Contract;

// What a site adds to the catalog the bundle ships, declared once rather than by overriding BookCrudController: the vocabulary its medias and its editions use, and the fields its own books carry. Implemented by the consuming app, collected through the "book.customization_provider" tag
interface BookCustomizationProviderInterface
{
    /**
     * The kinds of files a book of this site holds ("page", "audio_mp3"...), which the media form offers as choices. An empty array falls back to c975L\BookBundle\Enum\BookMediaKind, the vocabulary the bundle sets and reads itself - a site only declares this to name a file the bundle never heard of.
     *
     * @return array<string, string> kind => label (a plain label or a translation key of the "book" domain)
     */
    public function getMediaKinds(): array;

    /**
     * The editions this site publishes ("paper", "audio"...), each a c975L\BookBundle\Entity\BookEdition row carrying its own ISBN. An edition is a format, never a version of the text: a book rewritten, revised or newly illustrated is a book of its own, linked to the one it replaces (see c975L\BookBundle\Entity\Book::$newerVersion). An empty array falls back to c975L\BookBundle\Enum\BookEditionKind, the paper/digital/audio a catalog holds by default.
     *
     * @return array<string, string> kind => label (a plain label or a translation key of the "book" domain)
     */
    public function getEditionKinds(): array;

    /**
     * The platforms this site's books are sold, read or listened to on ("epub_fnac", "audio_gplay"...), each declaring what it is called, which card it prints in (see c975L\BookBundle\Enum\BookLinkGroup) and the icon standing for it - an asset path the site ships, or one of the bundle's own under "bundles/c975lbook/icons/". An empty array falls back to c975L\BookBundle\Enum\BookLinkKind, the stores and podcast apps a catalog holds by default.
     *
     * @return array<string, array{label: string, group: string, icon: string}> kind => platform
     */
    public function getLinkKinds(): array;

    /**
     * How this site lays out a book's page: which sections come in which order, under which name, with which look, and with what of its own added inside.
     * Keyed by the section's own key - "resume", "apercu", "extracts", "crowdfunding", "shops", "podcasts", "presse", "marketing", "informations".
     * The order of the array is the order of the page, a key left out coming after the declared ones, in the bundle's own order, and keeping the name and the plain card the bundle gives it. A site declaring nothing gets exactly the page it always had.
     * "anchor" and "label" are looked up in "domain" (the "book" catalog unless the site names its own) in the book's own language, an untranslated one printing as it stands - which is what lets a plain word be given here. "icon" is what c975LUi:Card:Card takes, "animation" what UiBundle's animate-scroll.js reads (the very effect a block carries, see its "animation" field), and "template" a fragment of the site's, included inside the card with the book at hand.
     * "hero" is no section either - it is the page's opening - but a "template" declared under it is included right under the title, which is where a site says what its own books carry ("Sur une idée de : …").
     * A key none of those, carrying a "template", is a section of the site's own: a rubric a single catalog holds, printed by that fragment alone, at the rank the declaration gives it. "form" is the shape it takes (see book/_section.html.twig - a card unless it says otherwise), and "field" the property it is written in, which is what gives it the same editor's pencil as the bundle's own sections - "data" for a rubric held in the site's own fields (see getDataFormType()).
     *
     * @return array<string, array{anchor?: string, label?: string, domain?: string, icon?: array<int, string|int>, animation?: string, template?: string, form?: string, field?: string}>
     */
    public function getSections(): array;

    /**
     * A plain form type mapped on Book::$data, holding the fields this site adds to a book and no other site has - the same way a block declares the form of its own data (see UiBundle's "ui.block" tag). Null for a site adding none.
     *
     * @return class-string|null
     */
    public function getDataFormType(): ?string;
}
