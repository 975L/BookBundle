<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Enum;

// The trade classifications a category can be filed under, each store reading its own: CLIL for the French book trade, Thema internationally, BISAC for the American stores (Apple, Kobo, Google). The value is the key in BookCategory::$codes, the ONIX scheme what the feed writes (code list 27)
enum BookSubjectScheme: string
{
    case Clil = 'clil';
    case Thema = 'thema';
    case Bisac = 'bisac';

    // The translation key of the scheme's name, in the "book" domain
    public function label(): string
    {
        return 'label.subject_scheme_' . $this->value;
    }

    // The ONIX SubjectSchemeIdentifier of a code of this scheme. A Thema qualifier is a scheme of its own, told by its first digit - place, language, time, purpose, age, style
    public function onixScheme(string $code): string
    {
        return match ($this) {
            self::Clil => '29',
            self::Bisac => '10',
            self::Thema => match ($code[0] ?? '') {
                '1' => '94',
                '2' => '95',
                '3' => '96',
                '4' => '97',
                '5' => '98',
                '6' => '99',
                default => '93',
            },
        };
    }
}
