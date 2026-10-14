<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Twig;

use Twig\Attribute\AsTwigFunction;

// What book/display.html.twig needs to hold a book's sections in the cache (see book/_sections.html.twig): how long the entry may live, and whether every section the site declares can be held at all
class BookSectionsCacheExtension
{
    // Until the next midnight: three sections compare a date with today's (the publication in Informations and Shops, the end of a crowdfunding), so a page cached on the eve still has to say the book is out the next morning
    #[AsTwigFunction('book_sections_cache_ttl')]
    public static function ttl(?\DateTimeImmutable $now = null): int
    {
        $now ??= new \DateTimeImmutable();

        return max(60, $now->modify('tomorrow')->getTimestamp() - $now->getTimestamp());
    }

    // False as soon as one section declares "cache" => false, or carries a "template" without "cache" => true (see BookCustomizationProviderInterface::getSections()): a fragment of the site's own may print something of one visitor, a form and its csrf token say, which would be served to every other one - so it is held only when the site says it can be
    /** @param iterable<array<string, mixed>> $sections */
    #[AsTwigFunction('book_sections_cacheable')]
    public static function cacheable(iterable $sections): bool
    {
        foreach ($sections as $section) {
            $cache = $section['cache'] ?? null;
            if (false === $cache || ('' !== (string) ($section['template'] ?? '') && true !== $cache)) {
                return false;
            }
        }

        return true;
    }
}
