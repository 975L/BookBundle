<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Enum;

// The files an edition is sold as, one of each at most (see BookEdition::getFileOf()): a digital edition's EPUB, PDF and printable booklet share its ISBN, a recorded one has its MP3 - a printed one has none. Beside them the covers, kept as drawn for the stores asking for their own size (see isSold())
enum BookEditionFileKind: string
{
    case Epub = 'epub';
    case Pdf = 'pdf';
    case Booklet = 'booklet';
    case Audio = 'audio';
    case CoverFront = 'cover_front';
    case CoverBack = 'cover_back';

    // The translation key of the file's name, in the "book" domain
    public function label(): string
    {
        return 'label.edition_file_' . $this->value;
    }

    // Whether the shop sells the file - the covers are handed to the stores and to the shop's pictures, never sold
    public function isSold(): bool
    {
        return !\in_array($this, [self::CoverFront, self::CoverBack], true);
    }

    // The files an edition of this kind holds, all of them while it has no kind yet
    /** @return list<self> */
    public static function forEdition(?string $editionKind): array
    {
        if (null === $editionKind || '' === $editionKind) {
            return self::cases();
        }

        return match (BookEditionKind::of($editionKind)) {
            BookEditionKind::Paper => [],
            BookEditionKind::Audio => [self::Audio, self::CoverFront],
            BookEditionKind::Digital => [self::Epub, self::Pdf, self::Booklet, self::CoverFront, self::CoverBack],
        };
    }

    /** @return list<string> the extensions a file of this kind is accepted in */
    public function extensions(): array
    {
        return match ($this) {
            self::Epub => ['epub'],
            self::Pdf, self::Booklet => ['pdf'],
            self::Audio => ['mp3', 'm4a'],
            self::CoverFront, self::CoverBack => ['jpg', 'jpeg'],
        };
    }
}
