<?php

namespace c975L\BookBundle\Enum;

// The platforms a book can be read, listened to or watched on. Held as an enum rather than as one column per platform: adding a store used to mean a column, a migration and a template edit on every site - here it is a case, and the row that carries it is a c975L\BookBundle\Entity\BookLink, which holds the address itself
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

    // The choices an EasyAdmin/Symfony choice field takes, label => case
    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->label()] = $case;
        }

        return $choices;
    }
}
