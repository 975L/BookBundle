<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Entity;

use c975L\BookBundle\Entity\BookMedia;
use PHPUnit\Framework\TestCase;

// UiBundle's Slider reads every media through the accessors its own entity carries - a media of this hierarchy answering none of them takes the page down, so the whole contract is checked here rather than the fields one by one
class MediaTest extends TestCase
{
    public function testAMediaReadsItsMimeTypeOffItsName(): void
    {
        $this->assertSame('image/webp', new BookMedia()->setName('medias/book/cover.webp')->getMimeType());
        $this->assertSame('video/mp4', new BookMedia()->setName('medias/book/trailer.MP4')->getMimeType());
        $this->assertSame('application/epub+zip', new BookMedia()->setName('medias/book/book.epub')->getMimeType());
    }

    // Whatever the Slider is handed must still be told apart from a video, so an unnamed or unknown file answers rather than breaking
    public function testAnUnknownExtensionFallsBackOnABinaryMimeType(): void
    {
        $this->assertSame('application/octet-stream', new BookMedia()->setName('medias/book/archive.xyz')->getMimeType());
        $this->assertSame('application/octet-stream', new BookMedia()->getMimeType());
    }

    public function testTheTitleStandsAsTheAlternativeText(): void
    {
        $this->assertSame('Couverture', new BookMedia()->setTitle('Couverture')->getAlt());
    }

    public function testAMediaAnswersEveryAccessorTheSliderReads(): void
    {
        $media = new BookMedia()->setName('medias/book/page-01.webp')->setTitle('Page 1');

        $this->assertNull($media->getLabel());
        $this->assertNull($media->getWidth());
        $this->assertNull($media->getHeight());
        $this->assertNull($media->getCredits());
        $this->assertSame([], $media->getCssClasses());
        $this->assertFalse($media->isAbove());
        $this->assertFalse($media->isRightsReserved());
    }
}
