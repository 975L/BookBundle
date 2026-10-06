<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Enum;

// The files an edition is sold as, one of each at most (see BookEdition::getFileOf()): a digital edition's EPUB, PDF and printable booklet share its ISBN, a recorded one has its MP3 - a printed one has none
enum BookEditionFileKind: string
{
    case Epub = 'epub';
    case Pdf = 'pdf';
    case Booklet = 'booklet';
    case Audio = 'audio';

    // The translation key of the file's name, in the "book" domain
    public function label(): string
    {
        return 'label.edition_file_' . $this->value;
    }

    // The files an edition of this kind is sold as, the four while it has no kind yet
    /** @return list<self> */
    public static function forEdition(?string $editionKind): array
    {
        if (null === $editionKind || '' === $editionKind) {
            return self::cases();
        }

        return match (BookEditionKind::of($editionKind)) {
            BookEditionKind::Paper => [],
            BookEditionKind::Audio => [self::Audio],
            BookEditionKind::Digital => [self::Epub, self::Pdf, self::Booklet],
        };
    }

    /** @return list<string> the extensions a file of this kind is accepted in */
    public function extensions(): array
    {
        return match ($this) {
            self::Epub => ['epub'],
            self::Pdf, self::Booklet => ['pdf'],
            self::Audio => ['mp3', 'm4a'],
        };
    }
}
