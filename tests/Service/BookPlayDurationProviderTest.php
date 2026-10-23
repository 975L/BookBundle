<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Service;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Entity\Media;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Service\AudioDurationReader;
use c975L\BookBundle\Service\BookPlayDurationProvider;
use PHPUnit\Framework\TestCase;

// A book's listen lasts as long as its recording, read from the mp3 first, and a book nobody may see has none
class BookPlayDurationProviderTest extends TestCase
{
    /** @var list<string> the media the reader was asked to measure */
    private array $read = [];

    private function provider(?Book $book): BookPlayDurationProvider
    {
        $repository = $this->createStub(BookRepository::class);
        $repository->method('findPublishedByIds')->willReturn(null === $book ? [] : [$book]);

        $reader = $this->createStub(AudioDurationReader::class);
        $reader->method('ofMedia')->willReturnCallback(function (Media $media): ?int {
            $this->read[] = (string) $media->getName();

            return 600;
        });

        return new BookPlayDurationProvider($repository, $reader);
    }

    public function testOnlyBooksAreMeasured(): void
    {
        $this->assertTrue($this->provider(null)->supports('book'));
        $this->assertFalse($this->provider(null)->supports('shop_product'));
    }

    // The ogg listed first is passed over for the mp3, the one format the reader measures, and only the mp3 is read
    public function testTheMp3IsMeasured(): void
    {
        $book = new Book()
            ->addMedia(new BookMedia()->setName('histoire.ogg'))
            ->addMedia(new BookMedia()->setName('histoire.mp3'));

        $this->assertSame(600, $this->provider($book)->getPlayDuration('book', 12));
        $this->assertSame(['histoire.mp3'], $this->read);
    }

    public function testABookWithNoMp3OrNotPublishedHasNoDuration(): void
    {
        $this->assertNull($this->provider(new Book()->addMedia(new BookMedia()->setName('couverture.jpg')))->getPlayDuration('book', 12));
        $this->assertNull($this->provider(new Book()->addMedia(new BookMedia()->setName('histoire.m4a')))->getPlayDuration('book', 12));
        $this->assertNull($this->provider(null)->getPlayDuration('book', 12));
    }
}
