<?php

namespace c975L\BookBundle\Contract;

// What a site adds to the catalog the bundle ships, declared once rather than by overriding BookCrudController: the vocabulary its medias and its editions use, and the fields its own books carry. Implemented by the consuming app, collected through the "book.customization_provider" tag
interface BookCustomizationProviderInterface
{
    // The kinds of files a book of this site holds ("page", "audio_mp3"...), which the media form offers as choices. An empty array falls back to c975L\BookBundle\Enum\BookMediaKind, the vocabulary the bundle sets and reads itself - a site only declares this to name a file the bundle never heard of.
    /** @return array<string, string> kind => label (a plain label or a translation key of the "book" domain) */
    public function getMediaKinds(): array;

    // The editions this site publishes ("paper", "audio"...), each a c975L\BookBundle\Entity\BookEdition row carrying its own ISBN. An edition is a format, never a version of the text: a book rewritten, revised or newly illustrated is a book of its own, linked to the one it replaces (see c975L\BookBundle\Entity\Book::$newerVersion). An empty array falls back to c975L\BookBundle\Enum\BookEditionKind, the paper/digital/audio a catalog holds by default.
    /** @return array<string, string> kind => label (a plain label or a translation key of the "book" domain) */
    public function getEditionKinds(): array;

    // The parts someone takes in this site's books beyond signing or drawing them ("narrator", "translator", "colourist"...), each a c975L\BookBundle\Entity\BookContributor row joining the book and the person. Author and illustrator are not declared here: a book holds each in a column of its own, being the two it inherits from its serie (see c975L\BookBundle\Entity\Book::getEffectiveAuthor()). An empty array falls back to c975L\BookBundle\Enum\BookContributorRole, the narrator and the translator a catalog credits by default.
    /** @return array<string, string> role => label (a plain label or a translation key of the "book" domain) */
    public function getContributorRoles(): array;

    // The platforms this site's books are sold, read or listened to on ("epub_fnac", "audio_gplay"...), each declaring what it is called, which card it prints in (see c975L\BookBundle\Enum\BookLinkGroup) and the icon standing for it - an asset path the site ships, or one of the bundle's own under "bundles/c975lbook/icons/". An empty array falls back to c975L\BookBundle\Enum\BookLinkKind, the stores and podcast apps a catalog holds by default.
    /** @return array<string, array{label: string, group: string, icon: string}> kind => platform */
    public function getLinkKinds(): array;

    // How this site lays out a book's page, keyed by the section's own key ("resume", "apercu", "extracts", "crowdfunding", "shops", "podcasts", "presse", "marketing", "informations", plus "hero" for the opening): the array's order is the page's, a key left out keeping the rank, name and card the bundle gives it, "anchor" and "label" being read in "domain" in the book's language, "icon" what c975LUi:Card:Card takes, "animation" what animate-scroll.js reads, and "template" a fragment of the site's - a key none of those, carrying one, is a rubric of the site's own, "form" its shape and "field" the property it is written in
    /** @return array<string, array{anchor?: string, label?: string, domain?: string, icon?: array<int, string|int>, animation?: string, template?: string, form?: string, field?: string}> */
    public function getSections(): array;

    // A plain form type mapped on Book::$data, holding the fields this site adds to a book and no other site has - the same way a block declares the form of its own data (see UiBundle's "ui.block" tag). Null for a site adding none.
    /** @return class-string|null */
    public function getDataFormType(): ?string;
}
