<?php

namespace c975L\BookBundle\Twig;

use c975L\BookBundle\Entity\BookContributor;
use c975L\BookBundle\Entity\BookEdition;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFunction;

// How an edition, and the part someone takes in a book, name themselves on a page. The kind stored is the site's own word ("illustrated_paper"), whose label only the vocabulary knows (see c975L\BookBundle\Contract\BookCustomizationProviderInterface) - a template guessing a translation key off the kind would print the key itself for every edition the bundle does not ship
class BookEditionExtension
{
    public function __construct(
        private readonly BookCustomizationRegistry $customizationRegistry,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // A label declared as a translation key is translated, one declared in plain words is printed as it stands - the translator returns any string it holds no entry for
    #[AsTwigFunction('book_edition_label')]
    public function label(BookEdition | string | null $edition, ?string $locale = null): string
    {
        $kind = $edition instanceof BookEdition ? (string) $edition->getKind() : (string) $edition;

        if ('' === $kind) {
            return '';
        }

        return $this->translator->trans($this->customizationRegistry->getEditionKinds()[$kind] ?? $kind, [], 'book', $locale);
    }

    // The same reading for the part someone takes in a book, whose word is the site's own just as much (see BookCustomizationProviderInterface::getContributorRoles())
    #[AsTwigFunction('book_role_label')]
    public function roleLabel(BookContributor | string | null $credit, ?string $locale = null): string
    {
        $role = $credit instanceof BookContributor ? (string) $credit->getRole() : (string) $credit;

        return $this->customizationRegistry->getRoleLabel($role, $locale);
    }
}
