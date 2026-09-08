<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Service;

use c975L\BookBundle\Service\BlockFixtureProvider;
use PHPUnit\Framework\TestCase;

class BlockFixtureProviderTest extends TestCase
{
    // Only the two kinds holding their own content: every other one queries the catalog live and is shown through GalleryShowcaseProvider instead
    public function testFixturesCoverTheKindsHoldingTheirOwnContent(): void
    {
        $fixtures = new BlockFixtureProvider()->getFixtures();

        $this->assertSame(['book_stores', 'book_reader'], array_keys($fixtures));
        $this->assertArrayHasKey('', $fixtures['book_reader']);
        $this->assertArrayHasKey('', $fixtures['book_stores']);
    }

    // A gallery entry is a sample an admin overwrites: it must name no real shelf, so every address stays an anchor going nowhere
    public function testStoreFixtureLeadsNowhere(): void
    {
        $items = new BlockFixtureProvider()->getFixtures()['book_stores']['']['items'];

        $this->assertNotEmpty($items);
        foreach ($items as $item) {
            $this->assertStringStartsWith('#', $item['url']);
            $this->assertNotSame('', $item['kind']);
        }
    }

    // The gallery turns pages from these, so a cue out of order would read as a bug in the reader itself
    public function testCuesAreOrdered(): void
    {
        $cues = new BlockFixtureProvider()->getFixtures()['book_reader']['']['cues'];
        $starts = array_column($cues, 'start');

        $sorted = $starts;
        sort($sorted);
        $this->assertSame($sorted, $starts);
        $this->assertSame([1, 2, 3], array_column($cues, 'page'));
    }
}
