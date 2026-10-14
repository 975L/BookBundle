<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Twig;

use c975L\BookBundle\Twig\BookSectionsCacheExtension;
use PHPUnit\Framework\TestCase;

// A book's sections are held until midnight, and not at all once a section of the site's asks not to be, or prints a fragment that never said it can be
class BookSectionsCacheExtensionTest extends TestCase
{
    public function testLivesUntilTheNextMidnight(): void
    {
        $this->assertSame(3600, BookSectionsCacheExtension::ttl(new \DateTimeImmutable('2026-10-14 23:00:00')));
    }

    // A minute at least: a page built on the stroke of midnight is not rebuilt for every visitor of that second
    public function testLivesOneMinuteAtLeast(): void
    {
        $this->assertSame(60, BookSectionsCacheExtension::ttl(new \DateTimeImmutable('2026-10-14 23:59:50')));
    }

    public function testASectionAskingNotToBeCachedKeepsThemAllLive(): void
    {
        $this->assertTrue(BookSectionsCacheExtension::cacheable([['key' => 'resume'], ['key' => 'shops', 'cache' => true]]));
        $this->assertFalse(BookSectionsCacheExtension::cacheable([['key' => 'resume'], ['key' => 'idea', 'cache' => false]]));
    }

    // A fragment of the site's is held only when it says it can be: nothing tells what it prints
    public function testASiteFragmentIsCachedOnlyWhenItSaysSo(): void
    {
        $this->assertFalse(BookSectionsCacheExtension::cacheable([['key' => 'resume'], ['key' => 'idea', 'template' => 'book/_idea.html.twig']]));
        $this->assertTrue(BookSectionsCacheExtension::cacheable([['key' => 'resume'], ['key' => 'idea', 'template' => 'book/_idea.html.twig', 'cache' => true]]));
    }
}
