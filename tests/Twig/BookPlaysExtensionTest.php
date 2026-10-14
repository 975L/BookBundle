<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Twig;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Twig\BookPlaysExtension;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Service\PlayService;
use PHPUnit\Framework\TestCase;

// A book's listens are said from the site's minimum on, and never for a book not saved yet
class BookPlaysExtensionTest extends TestCase
{
    private function extension(int $plays, string $minimum = '50'): BookPlaysExtension
    {
        $playService = $this->createStub(PlayService::class);
        $playService->method('totals')->willReturnCallback(static fn (string $ownerType, array $ids): array => array_fill_keys($ids, $plays));

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($minimum);

        return new BookPlaysExtension($playService, $configService);
    }

    private function book(?int $id = 12): Book
    {
        $book = new Book();
        if (null !== $id) {
            new \ReflectionProperty(Book::class, 'id')->setValue($book, $id);
        }

        return $book;
    }

    public function testSaysTheCountFromTheMinimumOn(): void
    {
        $this->assertSame(50, $this->extension(50)->plays($this->book()));
        $this->assertNull($this->extension(49)->plays($this->book()));
    }

    // No minimum set: any listen at all is said
    public function testAnEmptyMinimumSaysAnyListen(): void
    {
        $this->assertSame(1, $this->extension(1, '')->plays($this->book()));
        $this->assertNull($this->extension(0, '')->plays($this->book()));
    }

    // The same rule handed to whatever has a count already, the listening pages as data
    public function testShownAppliesTheMinimumToAnyCount(): void
    {
        $this->assertSame(50, $this->extension(0)->shown(50));
        $this->assertNull($this->extension(0)->shown(3));
    }

    public function testABookNotSavedYetSaysNothing(): void
    {
        $this->assertNull($this->extension(80)->plays($this->book(null)));
    }
}
