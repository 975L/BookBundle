<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Enum;

// Where an edition is handed out, ticked on the edition itself: a book may be in the public ONIX the bookshops read and not on Google, sold in the site's shop and nowhere else
enum BookChannel: string
{
    // The site's own shop, which the catalog writes the products of (see the shop's contract)
    case Shop = 'shop';
    // The public ONIX feed the bookshops and distributors read (see OnixController)
    case Onix = 'onix';
    // Google Play Books, fetching the ebooks on its own (see GooglePlayFeedController)
    case Google = 'google';
    // Apple Books, kept for the feed to come: ticking it sends nothing yet
    case Apple = 'apple';

    // The translation key of the channel's name, in the "book" domain
    public function label(): string
    {
        return 'label.channel_' . $this->value;
    }

    /** @return array<string, string> label => value, as a choice field takes them */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->label()] = $case->value;
        }

        return $choices;
    }
}
