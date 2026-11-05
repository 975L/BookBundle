<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Listener;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Entity\BookPrintable;
use c975L\BookBundle\Listener\BookPrintableNameListener;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use c975L\BookBundle\Service\BookPrintableNamer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Mapping\PropertyMapping;

// A document to print gets its prefix right before it is named, any other media keeping its own path
class BookPrintableNameListenerTest extends TestCase
{
    private function listener(): BookPrintableNameListener
    {
        $registry = $this->createStub(BookCustomizationRegistry::class);
        $registry->method('getSections')->willReturn(['printables' => ['filename' => 'Activity']]);
        $registry->method('getPrintableKinds')->willReturn([]);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new BookPrintableNameListener(new BookPrintableNamer($registry, new AsciiSlugger(), $translator));
    }

    public function testADocumentToPrintIsPrefixed(): void
    {
        $printable = new BookPrintable()->setBook(new Book()->setSlug('item-1'));

        $this->listener()->onPreUpload(new Event($printable, new PropertyMapping('file', 'name')));

        $this->assertSame('medias/book/printables/activity-item-1', $printable->getVichMediaPath());
    }

    public function testAnyOtherMediaIsLeftAlone(): void
    {
        $media = new BookMedia()->setBook(new Book()->setSlug('item-1'));
        $path = $media->getVichMediaPath();

        $this->listener()->onPreUpload(new Event($media, new PropertyMapping('file', 'name')));

        $this->assertSame($path, $media->getVichMediaPath());
    }
}
