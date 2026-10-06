<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Enum;

use c975L\BookBundle\Enum\BookEditionFileKind;
use PHPUnit\Framework\TestCase;

class BookEditionFileKindTest extends TestCase
{
    // A printed book is sold as no file, a recording as its MP3, an ebook as its EPUB, its PDF and its booklet
    public function testTheFilesFollowTheKindOfTheEdition(): void
    {
        $this->assertSame([], BookEditionFileKind::forEdition('paper'));
        $this->assertSame([BookEditionFileKind::Audio, BookEditionFileKind::CoverFront], BookEditionFileKind::forEdition('audio'));
        $this->assertSame([BookEditionFileKind::Epub, BookEditionFileKind::Pdf, BookEditionFileKind::Booklet, BookEditionFileKind::CoverFront, BookEditionFileKind::CoverBack], BookEditionFileKind::forEdition('digital'));
    }

    // An edition whose kind is not chosen yet offers every slot
    public function testAnEditionWithoutKindOffersEveryFile(): void
    {
        $this->assertSame(BookEditionFileKind::cases(), BookEditionFileKind::forEdition(null));
        $this->assertSame(BookEditionFileKind::cases(), BookEditionFileKind::forEdition(''));
    }

    public function testEachKindNamesItsExtensionsAndItsLabel(): void
    {
        $this->assertSame(['epub'], BookEditionFileKind::Epub->extensions());
        $this->assertSame(['pdf'], BookEditionFileKind::Booklet->extensions());
        $this->assertSame(['mp3', 'm4a'], BookEditionFileKind::Audio->extensions());
        $this->assertSame(['jpg', 'jpeg'], BookEditionFileKind::CoverFront->extensions());
        $this->assertSame('label.edition_file_booklet', BookEditionFileKind::Booklet->label());
    }

    // The covers go to the stores and the shop's pictures, never on sale
    public function testOnlyTheCoversAreNotSold(): void
    {
        $this->assertFalse(BookEditionFileKind::CoverFront->isSold());
        $this->assertFalse(BookEditionFileKind::CoverBack->isSold());
        $this->assertTrue(BookEditionFileKind::Epub->isSold());
    }
}
