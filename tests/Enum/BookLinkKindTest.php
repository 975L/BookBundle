<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Enum;

use c975L\BookBundle\Enum\BookLinkGroup;
use c975L\BookBundle\Enum\BookLinkKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BookLinkKindTest extends TestCase
{
    /** @return iterable<string, array{BookLinkKind}> */
    public static function kinds(): iterable
    {
        foreach (BookLinkKind::cases() as $kind) {
            yield $kind->value => [$kind];
        }
    }

    // A platform added to the enum without its label, its icon or its group would go unnoticed until a page prints it
    #[DataProvider('kinds')]
    public function testEveryPlatformIsNamed(BookLinkKind $kind): void
    {
        $this->assertNotSame('', $kind->label());
        $this->assertInstanceOf(BookLinkGroup::class, $kind->group());
    }

    #[DataProvider('kinds')]
    public function testEveryPlatformIconIsShippedByTheBundle(BookLinkKind $kind): void
    {
        $icon = $kind->icon();
        $this->assertStringStartsWith('bundles/c975lbook/icons/', $icon);
        $this->assertFileExists(dirname(__DIR__, 2) . '/public/icons/' . basename($icon));
    }

    public function testTwoPlatformsNeverShareALabel(): void
    {
        $labels = array_map(static fn (BookLinkKind $kind) => $kind->label(), BookLinkKind::cases());

        $this->assertSame($labels, array_unique($labels));
    }

    public function testChoicesGiveEveryPlatformAsItsLabel(): void
    {
        $choices = BookLinkKind::choices();

        $this->assertCount(count(BookLinkKind::cases()), $choices);
        $this->assertSame(BookLinkKind::EpubKobo, $choices['Kobo']);
        $this->assertSame(BookLinkKind::VideoYoutube, $choices['YouTube']);
    }

    public function testAGroupHoldsThePlatformsAPagePrintsTogether(): void
    {
        $this->assertSame(BookLinkGroup::Epub, BookLinkKind::EpubFnac->group());
        $this->assertSame(BookLinkGroup::Audio, BookLinkKind::AudioGplay->group());
        $this->assertSame(BookLinkGroup::Podcast, BookLinkKind::PodcastDeezer->group());
        $this->assertSame(BookLinkGroup::Video, BookLinkKind::VideoYoutube->group());
    }
}
