<?php

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Contract\BookCustomizationProviderInterface;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookLink;
use c975L\BookBundle\Enum\BookEditionKind;
use c975L\BookBundle\Enum\BookLinkKind;
use c975L\BookBundle\Enum\BookMediaKind;
use c975L\ConfigBundle\Management\ProviderMerger;
use Symfony\Contracts\Translation\TranslatorInterface;

// Merges what every site declares about its catalog (see BookCustomizationProviderInterface), so the forms and the CRUD read one vocabulary whether the app customizes anything or not
class BookCustomizationRegistry
{
    /** @param iterable<BookCustomizationProviderInterface> $providers */
    public function __construct(
        private readonly iterable $providers,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // The bundle's own vocabulary when the site names no file of its own, which is what a catalog holds until it stores a file the bundle never heard of - the same fallback the editions and the platforms have. Never empty, so the kind field is always offered: the bundle sets three of these words itself and reads the others to decide what a page prints (see BookMediaKind)
    /** @return array<string, string> kind => label */
    public function getMediaKinds(): array
    {
        $kinds = ProviderMerger::merge($this->providers, static fn (BookCustomizationProviderInterface $provider) => $provider->getMediaKinds());

        return [] === $kinds ? BookMediaKind::defaults() : $kinds;
    }

    // The bundle's own paper/digital/audio when the site names no edition of its own, which is what a catalog holds until it publishes an illustrated or a translated one
    /** @return array<string, string> kind => label */
    public function getEditionKinds(): array
    {
        $kinds = ProviderMerger::merge($this->providers, static fn (BookCustomizationProviderInterface $provider) => $provider->getEditionKinds());

        return [] === $kinds ? BookEditionKind::defaults() : $kinds;
    }

    // The bundle's own stores and podcast apps when the site names no platform of its own, which is what a catalog sells on until it opens a shop the bundle never heard of
    /** @return array<string, array{label: string, group: string, icon: string}> kind => platform */
    public function getLinkKinds(): array
    {
        $kinds = ProviderMerger::merge($this->providers, static fn (BookCustomizationProviderInterface $provider) => $provider->getLinkKinds());

        return [] === $kinds ? BookLinkKind::defaults() : $kinds;
    }

    // How a platform names itself on a page - its own brand, printed as it stands. Handed to the translator all the same: a brand is no key and comes back as is, where a common noun - the site's own shop - is said in the book's language. A kind the vocabulary does not hold prints as it is stored, which is what makes a forgotten declaration visible rather than silent
    public function getLinkLabel(BookLink | string | null $link, ?string $locale = null): string
    {
        $kind = $this->linkKind($link);
        $label = $this->getLinkKinds()[$kind]['label'] ?? $kind;

        return $this->translator->trans($label, [], 'book', $locale);
    }

    // The asset path of the icon standing for the platform, null for one declaring none
    public function getLinkIcon(BookLink | string | null $link): ?string
    {
        $icon = $this->getLinkKinds()[$this->linkKind($link)]['icon'] ?? null;

        return '' === $icon ? null : $icon;
    }

    // What the platform sells or plays, which is the card a page prints it in (see BookLinkGroup)
    public function getLinkGroup(BookLink | string | null $link): ?string
    {
        return $this->getLinkKinds()[$this->linkKind($link)]['group'] ?? null;
    }

    // The links of one group, which is how a page prints them: the stores in one card, the podcasts in another
    /** @return list<BookLink> */
    public function getLinksOf(Book $book, string $group): array
    {
        $links = [];

        foreach ($book->getLinks() as $link) {
            if ($this->getLinkGroup($link) === $group) {
                $links[] = $link;
            }
        }

        return $links;
    }

    private function linkKind(BookLink | string | null $link): string
    {
        return $link instanceof BookLink ? (string) $link->getKind() : (string) $link;
    }

    // How the site lays out a book's page - the order, the names and the look of its sections (see BookCustomizationProviderInterface::getSections()). Merged rather than taken from the first provider: a satellite bundle dressing the section it adds must not have to know about the site's own
    /** @return array<string, array<string, mixed>> key => declaration */
    public function getSections(): array
    {
        return ProviderMerger::merge($this->providers, static fn (BookCustomizationProviderInterface $provider) => $provider->getSections());
    }

    // The first form type declared, an app having one set of its own book fields and not one per provider
    /** @return class-string|null */
    public function getDataFormType(): ?string
    {
        foreach ($this->providers as $provider) {
            $formType = $provider->getDataFormType();

            if (null !== $formType) {
                return $formType;
            }
        }

        return null;
    }
}
