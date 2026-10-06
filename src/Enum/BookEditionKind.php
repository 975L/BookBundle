<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Enum;

// The editions a book is published in, used as the default vocabulary when no site declares its own (see c975L\BookBundle\Contract\BookCustomizationProviderInterface). A kind is stored as a plain string on c975L\BookBundle\Entity\BookEdition, so a site publishing an illustrated or a translated edition names it without the bundle having to know it
enum BookEditionKind: string
{
    case Paper = 'paper';
    case Digital = 'digital';
    case Audio = 'audio';

    // The translation key of the edition's name, in the "book" domain
    public function label(): string
    {
        return 'label.edition_' . $this->value;
    }

    // What a kind the site names stands for, matched on its words: "audio" is a recording, "digital", "ebook", "epub" or "pdf" a file, anything else a printed book - the safe guess, a file being what a store sells and a printed book never handed one
    public static function of(?string $kind): self
    {
        $kind = strtolower((string) $kind);

        return match (true) {
            str_contains($kind, 'audio') => self::Audio,
            str_contains($kind, 'digital'), str_contains($kind, 'ebook'), str_contains($kind, 'epub'), str_contains($kind, 'pdf') => self::Digital,
            default => self::Paper,
        };
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
