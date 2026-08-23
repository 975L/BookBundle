<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Assets;

use PHPUnit\Framework\TestCase;

// What a book's hero writes and does not write: nothing checks it at rendering, a functional test asking for a kernel and a database
class HeroMarkupTest extends TestCase
{
    // On a book's page the serie says where it comes from: it is named, neither painted nor clickable - its logo took a banner's room, and its link carried the reader away. The logo stays the serie page's own (Serie:Hero)
    public function testTheSerieIsNamedInTheHeroAndNeitherLinkedNorPainted(): void
    {
        $hero = $this->hero();

        $this->assertStringContainsString('book.serie.title', $hero);
        $this->assertStringNotContainsString('serie_display', $hero);
        $this->assertStringNotContainsString('book-hero__logo', $hero);
    }

    // Both buttons read on the section's key and write its real anchor: a site renaming its "Shops" card anchor lost the button silently, the link pointing at an anchor the page no longer wrote
    public function testTheButtonsGoByTheSectionKeyAndWriteItsOwnAnchor(): void
    {
        $hero = $this->hero();

        $this->assertStringContainsString('sectionsByKey.shops is defined', $hero);
        $this->assertStringContainsString('#{{ sectionsByKey.shops.anchor }}', $hero);
        $this->assertStringContainsString('sectionsByKey.podcasts is defined', $hero);
        $this->assertStringContainsString('#{{ sectionsByKey.podcasts.anchor }}', $hero);
        $this->assertStringNotContainsString("'shops' in anchors", $hero);
    }

    private function hero(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/components/Book/Hero.html.twig');
    }
}
