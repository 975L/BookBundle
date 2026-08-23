<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Contract;

use c975L\BookBundle\Entity\Book;

// What a site lists on its catalog page, when "the books whose own date has passed" is not what it means by out - a site reading a story as out on a date of its own, and ordering the list by that date, answers here rather than overriding the controller.
// Deliberately not part of BookCustomizationProviderInterface, which names the vocabulary of a catalog - the kinds, the platforms, the sections. Which rows a page lists, and in what order, is a query and not a word.
interface BookCatalogProviderInterface
{
    /**
     * The books the index lists, in the order it lists them. An empty list is an answer like any other; null hands the page back to the bundle's own reading.
     *
     * @return list<Book>|null
     */
    public function getBooks(): ?array;

    /**
     * The word this site gives its catalog, translated in the "book" catalog unless it holds none - "Les histoires" where the bundle says "Livres". Null keeps the bundle's own.
     */
    public function getTitle(): ?string;
}
