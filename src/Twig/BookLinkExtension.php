<?php

namespace c975L\BookBundle\Twig;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookLink;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use Twig\Attribute\AsTwigFunction;

// How a platform names and shows itself on a page, and which links a card holds. The kind stored is the site's own word ("epub_fnac"), whose label, icon and group only the vocabulary knows (see c975L\BookBundle\Contract\BookCustomizationProviderInterface) - the entity used to answer all three off an enum, which is what tied the bundle to the Fnac and to Spotify
class BookLinkExtension
{
    public function __construct(private readonly BookCustomizationRegistry $customizationRegistry)
    {
    }

    // The platform's own brand, printed as it stands and never translated - a kind the vocabulary does not hold prints as it is stored
    #[AsTwigFunction('book_link_label')]
    public function label(BookLink | string | null $link): string
    {
        return $this->customizationRegistry->getLinkLabel($link);
    }

    #[AsTwigFunction('book_link_icon')]
    public function icon(BookLink | string | null $link): ?string
    {
        return $this->customizationRegistry->getLinkIcon($link);
    }

    // The links of one group, which is how a page prints them: the stores in one card, the podcasts in another (see c975L\BookBundle\Enum\BookLinkGroup)
    /** @return list<BookLink> */
    #[AsTwigFunction('book_links_of')]
    public function linksOf(Book $book, string ...$groups): array
    {
        $links = [];

        foreach ($groups as $group) {
            foreach ($this->customizationRegistry->getLinksOf($book, $group) as $link) {
                $links[] = $link;
            }
        }

        return $links;
    }
}
