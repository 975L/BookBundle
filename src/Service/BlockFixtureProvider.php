<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

use c975L\UiBundle\Contract\BlockFixtureProviderInterface;

// Sample data for the block gallery. Only the two kinds holding their own content are here - "book_reader", whose medias BlockFixtureMediaAttacher fills from the placeholders, and "book_stores", whose platforms are stored on the block itself. The kinds querying the catalog live are rendered by GalleryShowcaseProvider instead
class BlockFixtureProvider implements BlockFixtureProviderInterface
{
    public function getFixtures(): array
    {
        return [
            // The platforms of the bundle's own vocabulary, at addresses leading nowhere: a gallery entry is a sample an admin overwrites, never a real shelf (see BookLinkKind)
            'book_stores' => [
                '' => [
                    'id' => 'gallery-book-stores-preview',
                    'title' => 'Où nous trouver',
                    'items' => [
                        ['kind' => 'epub_fnac', 'url' => '#store-fnac'],
                        ['kind' => 'epub_kobo', 'url' => '#store-kobo'],
                        ['kind' => 'epub_gplay', 'url' => '#store-google-play'],
                        ['kind' => 'epub_apple', 'url' => '#store-apple-books'],
                    ],
                ],
            ],
            'book_reader' => [
                '' => [
                    'id' => 'gallery-book-reader-preview',
                    'title' => 'Lu par l\'auteur',
                    // One page every four seconds, the placeholder recording being what the gallery plays
                    'cues' => [
                        ['page' => 1, 'start' => 0],
                        ['page' => 2, 'start' => 4],
                        ['page' => 3, 'start' => 8],
                    ],
                    'autoAdvance' => true,
                ],
            ],
        ];
    }
}
