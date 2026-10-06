<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Command;

use c975L\BookBundle\Command\BookShopPublishCommand;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Service\BookShopPublisher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class BookShopPublishCommandTest extends TestCase
{
    // Each family once, through its latest book still in the catalog
    public function testEveryLatestBookIsWrittenIntoTheShop(): void
    {
        $books = [new Book(), new Book()];
        $repository = $this->createMock(BookRepository::class);
        $repository->expects($this->once())->method('findBy')->with(['newerVersion' => null, 'isDeleted' => false])->willReturn($books);
        $publisher = $this->createMock(BookShopPublisher::class);
        $publisher->method('isAvailable')->willReturn(true);
        $publisher->expects($this->exactly(2))->method('publish');

        $tester = new CommandTester(new BookShopPublishCommand($repository, $publisher));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('2 book(s) written', $tester->getDisplay());
    }

    public function testWithoutAShopNothingIsWritten(): void
    {
        $publisher = $this->createMock(BookShopPublisher::class);
        $publisher->method('isAvailable')->willReturn(false);
        $publisher->expects($this->never())->method('publish');

        $tester = new CommandTester(new BookShopPublishCommand($this->createStub(BookRepository::class), $publisher));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
    }
}
