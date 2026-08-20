<?php

namespace c975L\BookBundle\Contract;

// What a site adds to the catalog the bundle ships, declared once rather than by overriding BookCrudController: the vocabulary its medias and its editions use, and the fields its own books carry. Implemented by the consuming app, collected through the "book.customization_provider" tag
interface BookCustomizationProviderInterface
{
    /**
     * The kinds of files a book of this site holds ("page", "audio_mp3"...), which the media form offers as choices. An empty array leaves the field out of the form entirely, a book media then carrying no kind - the bundle declares no default, having none of its own.
     *
     * @return array<string, string> kind => label (a plain label or a translation key of the "book" domain)
     */
    public function getMediaKinds(): array;

    /**
     * The editions this site publishes ("illustrated_paper", "audio"...), each a c975L\BookBundle\Entity\BookEdition row carrying its own ISBN. An empty array falls back to c975L\BookBundle\Enum\BookEditionKind, the paper/digital/audio a catalog holds by default.
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
     * A plain form type mapped on Book::$data, holding the fields this site adds to a book and no other site has - the same way a block declares the form of its own data (see UiBundle's "ui.block" tag). Null for a site adding none.
     *
     * @return class-string|null
     */
    public function getDataFormType(): ?string;
}
