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
    public function testFixturesCoverTheReaderKindOnly(): void
    {
        $fixtures = new BlockFixtureProvider()->getFixtures();

        $this->assertSame(['book_reader'], array_keys($fixtures));
        $this->assertArrayHasKey('', $fixtures['book_reader']);
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
