<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Enum;

// What a planche's media holds, which decides where the page shows it: the panels read one by one, the whole page they were cut from, the square card a listing and a share show. Stored as a plain string on c975L\BookBundle\Entity\Media and left null wherever a site never told them apart - a planche whose medias carry no kind is drawn as one gallery, which is what this bundle has always done (see Strip:Medias)
enum StripMediaKind: string
{
    case Panel = 'case';
    case Page = 'page';
    case Thumbnail = 'thumbnail';
    case Card = 'card';

    // The translation key of the kind's name, in the "book" domain
    public function label(): string
    {
        return 'label.strip_media_' . $this->value;
    }

    /**
     * The kinds offered on a media's own form, each with the key naming it - the field stays free of a value the back-office cannot produce.
     *
     * @return array<string, string>
     */
    public static function choices(): array
    {
        $choices = [];

        foreach (self::cases() as $kind) {
            $choices[$kind->label()] = $kind->value;
        }

        return $choices;
    }
}
