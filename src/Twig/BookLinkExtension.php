<?php

namespace c975L\BookBundle\Twig;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookLink;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Twig\Attribute\AsTwigFunction;

// How a platform names and shows itself on a page, and which links a card holds. The kind stored is the site's own word ("epub_fnac"), whose label, icon and group only the vocabulary knows (see c975L\BookBundle\Contract\BookCustomizationProviderInterface) - the entity used to answer all three off an enum, which is what tied the bundle to the Fnac and to Spotify
class BookLinkExtension
{
    // The parameter Google Play reads a partner from, and the ConfigBundle entry holding that partner - a sensitive setting of the site, never a value written into this bundle (see config/configs.json)
    private const string GPLAY_HOST = 'play.google.com';
    private const string GPLAY_PARAMETER = 'PAffiliateID';
    private const string GPLAY_CONFIG = 'book-gplay-affiliate';

    public function __construct(
        private readonly BookCustomizationRegistry $customizationRegistry,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    // The address to open, which is the one stored (see c975L\BookBundle\Entity\BookLink) plus, on a Google Play address only, the affiliate the site configured. Nothing is rebuilt from the book: the stored address opens the right page on its own, the identifier is only added on top of it, and never over one the address already carries
    #[AsTwigFunction('book_link_url')]
    public function url(BookLink | string | null $link): string
    {
        $url = $link instanceof BookLink ? (string) $link->getUrl() : (string) $link;
        $affiliate = $this->configService->get(self::GPLAY_CONFIG);

        if (!\is_string($affiliate) || '' === $affiliate || self::GPLAY_HOST !== parse_url($url, \PHP_URL_HOST)) {
            return $url;
        }

        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        if (\array_key_exists(self::GPLAY_PARAMETER, $query)) {
            return $url;
        }

        // The anchor stays last, an address carrying one otherwise ending with the identifier inside it
        [$address, $fragment] = array_pad(explode('#', $url, 2), 2, null);

        return $address
            . (str_contains($address, '?') ? '&' : '?')
            . self::GPLAY_PARAMETER . '=' . rawurlencode($affiliate)
            . (null === $fragment ? '' : '#' . $fragment);
    }

    // The platform's own brand, printed as it stands and never translated - a kind the vocabulary does not hold prints as it is stored
    #[AsTwigFunction('book_link_label')]
    public function label(BookLink | string | null $link, ?string $locale = null): string
    {
        return $this->customizationRegistry->getLinkLabel($link, $locale);
    }

    #[AsTwigFunction('book_link_icon')]
    public function icon(BookLink | string | null $link): ?string
    {
        return $this->customizationRegistry->getLinkIcon($link);
    }

    // The links of one or several groups, which is how a page prints them: the stores in one card, the podcasts in another (see c975L\BookBundle\Enum\BookLinkGroup)
    /** @return list<BookLink> */
    #[AsTwigFunction('book_links_of')]
    public function linksOf(Book $book, string ...$groups): array
    {
        $links = [];

        // Read once in the book's own order (see Book::$links, sorted on position), rather than group after group: a card holding two groups printed the audiobooks before the podcasts whatever the order the back office had put them in
        foreach ($book->getLinks() as $link) {
            if (\in_array($this->customizationRegistry->getLinkGroup($link), $groups, true)) {
                $links[] = $link;
            }
        }

        return $links;
    }
}
