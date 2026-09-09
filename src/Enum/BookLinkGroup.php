<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Enum;

// What a link leads to, which is the card a book's page prints it in - the stores together, the podcasts together
// "epub" names the shops card and not a format: the site's own shop stands there beside the digital bookshops, and it is the same gesture for a reader - buying the book (see BookLinkKind::Shop)
enum BookLinkGroup: string
{
    case Epub = 'epub';
    case Audio = 'audio';
    case Podcast = 'podcast';
    case Video = 'video';
}
