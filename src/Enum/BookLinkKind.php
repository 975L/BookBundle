<?php

namespace c975L\BookBundle\Enum;

// The platforms a book can be read, listened to or watched on, used as the default vocabulary when no site declares its own (see c975L\BookBundle\Contract\BookCustomizationProviderInterface). A kind is stored as a plain string on c975L\BookBundle\Entity\BookLink, so a site selling on a store the bundle never heard of names it without the bundle having to know it - the same move the editions made
enum BookLinkKind: string
{
    case EpubGplay = 'epub_gplay';
    case EpubFnac = 'epub_fnac';
    case EpubKobo = 'epub_kobo';
    case EpubApple = 'epub_apple';
    case AudioGplay = 'audio_gplay';
    case PodcastSpotify = 'podcast_spotify';
    case PodcastDeezer = 'podcast_deezer';
    case PodcastApple = 'podcast_apple';
    case VideoYoutube = 'video_youtube';

    // What the platform sells or plays, which is how a page groups its buttons - the digital stores in one card, the podcasts in another
    public function group(): BookLinkGroup
    {
        return match ($this) {
            self::EpubGplay, self::EpubFnac, self::EpubKobo, self::EpubApple => BookLinkGroup::Epub,
            self::AudioGplay => BookLinkGroup::Audio,
            self::PodcastSpotify, self::PodcastDeezer, self::PodcastApple => BookLinkGroup::Podcast,
            self::VideoYoutube => BookLinkGroup::Video,
        };
    }

    // Shown as-is, a platform's name being its own brand and never translated
    public function label(): string
    {
        return match ($this) {
            self::EpubGplay => 'Google Play Livres',
            self::EpubFnac => 'Fnac',
            self::EpubKobo => 'Kobo',
            self::EpubApple => 'Apple Books',
            self::AudioGplay => 'Google Play Livres audio',
            self::PodcastSpotify => 'Spotify',
            self::PodcastDeezer => 'Deezer',
            self::PodcastApple => 'Apple Podcasts',
            self::VideoYoutube => 'YouTube',
        };
    }

    // The svg shipped under the bundle's public/icons, given as the asset path a template hands to c975LUi:Image:Link
    public function icon(): string
    {
        return 'bundles/c975lbook/icons/' . match ($this) {
            self::EpubGplay, self::AudioGplay => 'google-play.svg',
            self::EpubFnac => 'fnac.svg',
            self::EpubKobo => 'kobo.svg',
            self::EpubApple => 'apple.svg',
            self::PodcastSpotify => 'spotify.svg',
            self::PodcastDeezer => 'deezer.svg',
            self::PodcastApple => 'itunes.svg',
            self::VideoYoutube => 'youtube.svg',
        };
    }

    // The vocabulary a site reads when it declares none of its own, in the shape a provider declares one (see BookCustomizationProviderInterface::getLinkKinds())
    /** @return array<string, array{label: string, group: string, icon: string}> kind => platform */
    public static function defaults(): array
    {
        $defaults = [];
        foreach (self::cases() as $case) {
            $defaults[$case->value] = [
                'label' => $case->label(),
                'group' => $case->group()->value,
                'icon' => $case->icon(),
            ];
        }

        return $defaults;
    }
}
