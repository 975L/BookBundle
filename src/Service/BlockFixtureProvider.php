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

// Sample data for the block gallery. Only the one kind holding its own content is here - "book_reader", whose medias BlockFixtureMediaAttacher fills from the placeholders. The kinds querying the catalog live are rendered by GalleryShowcaseProvider instead
class BlockFixtureProvider implements BlockFixtureProviderInterface
{
    public function getFixtures(): array
    {
        return [
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
