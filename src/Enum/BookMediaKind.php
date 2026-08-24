<?php

namespace c975L\BookBundle\Enum;

// What a book's files are, used as the default vocabulary when no site declares its own (see c975L\BookBundle\Contract\BookCustomizationProviderInterface). A kind is stored as a plain string on c975L\BookBundle\Entity\Media, so a site holding a file this bundle never heard of names it without the bundle having to know it - the same move the editions and the platforms made
// These words are the bundle's own reserved ones all the same: it already sets three of them itself (see Book::addCover() and its siblings) and reads the others to decide what a page prints, so a site renaming them renames what its own pages find
enum BookMediaKind: string
{
    // The image standing for the book wherever it is named - its page, its card, a rail listing it, the social card a shared link is drawn with. A book has one image to introduce itself, not two
    case Cover = 'cover';
    case CoverBack = 'cover_back';
    // The image the book's page opens on, behind its header
    case Background = 'background';
    case Page = 'page';
    // The pages a reader leafs through before buying
    case Extract = 'extract';
    // The video flicking through the book, which its page opens on rather than announces
    case Flipbook = 'flipbook';
    case AudioMp3 = 'audio_mp3';
    case AudioOgg = 'audio_ogg';

    // The translation key of the file's name, in the "book" domain
    public function label(): string
    {
        return 'label.media_' . $this->value;
    }

    /** @return array<string, string> kind => label */
    public static function defaults(): array
    {
        $defaults = [];
        foreach (self::cases() as $case) {
            $defaults[$case->value] = $case->label();
        }

        return $defaults;
    }
}
