<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Twig;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\Media;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Enum\BookMediaKind;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFunction;

// Which sections a display page actually holds, in the order it lays them out. Read twice by each of those pages - once by the hero, which offers the ones a reader comes for, once to decide what to render - so a button is never offered for a section the page left out, and a section never rendered without a name of its own. A serie's page still draws its summary of anchors from this (<twig:c975LUi:Text:Toc>); a book's has none, its hero saying the same in four verbs
class BookSectionsExtension
{
    // What the book's two cards claim: the trailer is watched under "Videos", the podcast video under "Listen". A version carrying them therefore does not print them a second time under its own name
    public const array CARD_KINDS = [BookMediaKind::Trailer->value, BookMediaKind::Podcast->value];

    // The sections this bundle prints itself, plus the two parts of the page that are no section (the hero and the summary). A site declaring any other key is naming a section of its own, which this bundle knows nothing about but the fragment rendering it
    // "edition" and "serie" are listed without being printed: the contract announced them for a while, and a site having declared them then would otherwise open a ghost card
    private const array OWN_KEYS = [
        'hero',
        'resume',
        'extracts',
        'apercu',
        'crowdfunding',
        'shops',
        'podcasts',
        'presse',
        'marketing',
        'informations',
        'edition',
        'serie',
    ];

    public function __construct(
        private readonly BookCustomizationRegistry $customizationRegistry,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[AsTwigFunction('book_sections')]
    public function book(Book $book): array
    {
        // A book is read in its own language, whatever the language the visitor reached it in
        $locale = $book->getLanguage();
        $shops = $book->getPublished() instanceof \DateTimeInterface && $book->getPublished()->format('Ymd') <= date('Ymd');

        // The gestures the page offers follow the order the hero offers them in: read, listen, watch, then buy - a reader tastes the book before paying for it, so the hero's blue button leads lower than the other three
        $sections = array_merge(
            // The summary is no section: it is the sentence the book opens on, printed under the hero by the page itself (Book:Resume) - with no title and no anchor, it has nothing to do in the table of contents
            $this->sections([
                // The pages a reader leafs through before buying (Book::getExtracts())
                'extracts' => ['label.read', !$book->getExtracts()->isEmpty()],
                // Where the book is listened to: the recording itself as much as the platforms carrying it, the card holding both
                'podcasts' => ['label.podcasts', [] !== self::audioMedias($book) || [] !== self::mediasOfKind($book, BookMediaKind::Podcast->value) || [] !== $this->customizationRegistry->getLinksOf($book, 'audio') || [] !== $this->customizationRegistry->getLinksOf($book, 'podcast')],
                // The videos the book holds as files, and the platforms it is watched on - one card, the way the stores and the podcast apps share theirs. The "video" group had a name and a place in the vocabulary and was printed by nothing
                'apercu' => ['label.watch', !$book->getVideos()->isEmpty() || [] !== self::mediasOfKind($book, BookMediaKind::Trailer->value) || [] !== $this->customizationRegistry->getLinksOf($book, 'video')],
            ], $locale),
            $this->sections([
                // Where a book not yet paid for is helped along, which comes before where a book already out is bought
                'crowdfunding' => ['label.crowdfunding', '' !== (string) $book->getCrowdfunding()],
                'shops' => ['label.buy', $shops && [] !== $this->customizationRegistry->getLinksOf($book, 'epub')],
                'presse' => ['label.presse', !$book->getPresses()->isEmpty()],
                'marketing' => ['label.marketing', !$book->getMarketings()->isEmpty()],
                // Always: a book with no field filled in still says when it comes out and who wrote it
                'informations' => ['label.informations', true],
            ], $locale),
        );

        return $this->laidOut($sections, $locale);
    }

    // The site's own layout laid over the bundle's: its order, its words for each section, its icon and its entrance effect, and the fragment it adds inside the card (see BookCustomizationProviderInterface::getSections()). A site declaring nothing gets the list back untouched
    /**
     * @param array<int, array<string, mixed>> $sections
     *
     * @return array<int, array<string, mixed>>
     */
    private function laidOut(array $sections, ?string $locale): array
    {
        $declared = $this->customizationRegistry->getSections();

        if ([] === $declared) {
            return $sections;
        }

        $order = array_keys($declared);
        $laidOut = [];

        foreach ($sections as $position => $section) {
            $own = $declared[$section['key']] ?? [];
            $rank = array_search($section['key'], $order, true);

            // A section the site says nothing about comes after the ones it placed, in the order this bundle lays them out
            $laidOut[] = [false === $rank ? \count($order) : $rank, $position, $this->dressed($section, $own, $locale)];
        }

        foreach ($this->declaredOwn($declared, $locale) as $rank => $section) {
            // Before the bundle's own sections of the same rank, there being none: a rank belongs to one key, and the site placed this one itself
            $laidOut[] = [$rank, -1, $section];
        }

        usort($laidOut, static fn (array $left, array $right): int => [$left[0], $left[1]] <=> [$right[0], $right[1]]);

        return array_column($laidOut, 2);
    }

    // The sections the site adds and this bundle has none of - a rubric a single catalog holds, written in a fragment of its own (see BookCustomizationProviderInterface::getSections()). Only a declaration carrying a "template" opens one: without it the card would print titled and empty
    /**
     * @param array<string, array<string, mixed>> $declared
     *
     * @return array<int, array<string, mixed>> rank => section
     */
    private function declaredOwn(array $declared, ?string $locale): array
    {
        $own = [];

        foreach (array_keys($declared) as $rank => $key) {
            if (\in_array($key, self::OWN_KEYS, true) || '' === (string) ($declared[$key]['template'] ?? '')) {
                continue;
            }

            $own[$rank] = $this->dressed(['key' => $key, 'anchor' => $key, 'label' => ''], $declared[$key], $locale);
        }

        return $own;
    }

    // The anchor and the label are translation keys as much as plain words: an untranslated one comes back as it stands, which is what lets a site hand over "Boutiques" as readily as "label.buy"
    /**
     * @param array<string, mixed> $section
     * @param array<string, mixed> $own
     *
     * @return array<string, mixed>
     */
    private function dressed(array $section, array $own, ?string $locale): array
    {
        $domain = \is_string($own['domain'] ?? null) ? $own['domain'] : 'book';

        foreach (['anchor', 'label', 'intro'] as $name) {
            if (\is_string($own[$name] ?? null) && '' !== $own[$name]) {
                $section[$name] = $this->translator->trans($own[$name], [], $domain, $locale);
            }
        }

        // "form" is what the card takes shape as (see book/_section.html.twig), a site's own section being a card unless it says otherwise
        foreach (['icon', 'animation', 'template', 'form'] as $name) {
            if (isset($own[$name]) && [] !== $own[$name] && '' !== $own[$name]) {
                $section[$name] = $own[$name];
            }
        }

        return $section;
    }

    // The fragment a site declared for one of the page's parts, whether or not that part is a section this bundle prints - the hero is no section, and a site naming a rubric of its own inside it is naming it here all the same (see BookCustomizationProviderInterface::getSections()). An empty string where nothing is declared, which is what lets a template ask without guarding twice
    #[AsTwigFunction('book_section_template')]
    public function sectionTemplate(string $key): string
    {
        $declared = $this->customizationRegistry->getSections()[$key] ?? [];

        return \is_string($declared['template'] ?? null) ? $declared['template'] : '';
    }

    // Whatever the book is listened to as
    /**
     * @return list<Media>
     */
    #[AsTwigFunction('book_audio_medias')]
    public static function audioMedias(Book $book): array
    {
        return array_values(array_filter(
            $book->getMedias()->toArray(),
            static fn (Media $media): bool => str_starts_with($media->getMimeType(), 'audio/')
        ));
    }

    // The two kinds the book's cards print, read by the templates rather than rewritten by them (see Book:Podcasts and Book:Videos)
    /** @return list<string> */
    #[AsTwigFunction('book_card_kinds')]
    public static function cardKinds(): array
    {
        return self::CARD_KINDS;
    }

    // The files of a given kind, whatever version carries them - which book_media() does not do, reading only those of the book itself. The trailer and the podcast video are read this way: they are announced in a card of the book, wherever they were uploaded
    /**
     * @return list<Media>
     */
    #[AsTwigFunction('book_medias_of_kind')]
    public static function mediasOfKind(Book $book, string ...$kinds): array
    {
        return array_values(array_filter(
            $book->getMedias()->toArray(),
            static fn (Media $media): bool => \in_array((string) $media->getKind(), $kinds, true)
        ));
    }

    // One of the images the book carries as a whole - "cover", "cover_back" or "background", each uploaded on a field of its own (see BookCrudController). Null where nothing was uploaded, which is what lets a page fall back rather than print a broken <img>
    #[AsTwigFunction('book_media')]
    public static function media(Book $book, string $kind): ?Media
    {
        return $book->mediasOfKind($kind)->first() ?: null;
    }

    // The images a book carries that stand for something other than itself: the backdrop its page opens on, its fourth cover. Neither is what a rail prints, so neither stands in for a cover a catalog has not uploaded yet
    private const array NOT_A_COVER = ['cover_back', 'background'];

    // What stands for the book wherever it is named - its page, its card, a rail listing it, the social card a shared link is drawn with. The cover uploaded on the field of its own first; then, for a book given none, the first image and not the first file: a catalog storing a recording or a flipbook before its cover would otherwise print an mp4 in an <img>
    // For want of an image of its own, the one of another version of the same text: it is the same book, and a newly illustrated version whose pages are not uploaded yet has nothing to show of itself (see BookVersionExtension). Without this last resort it showed everywhere under the "no cover" image
    #[AsTwigFunction('book_cover')]
    public static function cover(Book $book): ?Media
    {
        $cover = self::ownCover($book);
        if ($cover instanceof Media) {
            return $cover;
        }

        foreach (BookVersionExtension::versions($book) as $version) {
            $cover = self::ownCover($version);
            if ($cover instanceof Media) {
                return $cover;
            }
        }

        return null;
    }

    // What the book carries itself, borrowing nothing from anyone
    private static function ownCover(Book $book): ?Media
    {
        $cover = self::media($book, 'cover');
        if ($cover instanceof Media) {
            return $cover;
        }

        foreach ($book->getMedias() as $media) {
            if (str_starts_with($media->getMimeType(), 'image/') && !\in_array((string) $media->getKind(), self::NOT_A_COVER, true)) {
                return $media;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{anchor: string, label: string}>
     */
    #[AsTwigFunction('serie_sections')]
    public function serie(Serie $serie): array
    {
        // The planches are a section of their own, listed whole: a serie holding hundreds of them grows its list as the visitor scrolls rather than being cut to a handful (see serie/display.html.twig). The "book_serie_strips" block stays what shows a few of them on any other page
        // The summary is no section either, exactly as a book's is none: it is the sentence the serie opens on, laid under the hero without a title or an anchor (see Serie:Resume)
        return $this->sections([
            'books' => ['label.serie_books', !$serie->getBooks()->isEmpty()],
            'strips' => ['label.strips', !$serie->getStrips()->isEmpty()],
        ], $serie->getLanguage());
    }

    /**
     * @param array<string, array{0: string, 1: bool}> $candidates
     *
     * @return array<int, array{anchor: string, label: string}>
     */
    private function sections(array $candidates, ?string $locale): array
    {
        $sections = [];

        foreach ($candidates as $anchor => [$key, $isPresent]) {
            if ($isPresent) {
                // The key is what the section is named by everywhere else - the site's declaration, the pencil's map of fields - where the anchor is only what the page writes as an id, and the site may rename it
                $section = ['key' => $anchor, 'anchor' => $anchor, 'label' => $this->translator->trans($key, [], 'book', $locale)];
                $intro = $this->intro($key, $locale);

                if ('' !== $intro) {
                    $section['intro'] = $intro;
                }

                $sections[] = $section;
            }
        }

        return $sections;
    }

    // The sentence opening a section, under its name: what a reader finds there, said in one line. Generic and translated here, a site replacing it with its own as it replaces the name (see BookCustomizationProviderInterface::getSections())
    // Named after the section's label rather than listed somewhere: a section the catalog says nothing about has none, and its name is then returned as is by the translator - which is what tells an absent sentence from an empty one
    private function intro(string $label, ?string $locale): string
    {
        $key = $label . '-intro';
        $intro = $this->translator->trans($key, [], 'book', $locale);

        return $intro === $key ? '' : $intro;
    }
}
