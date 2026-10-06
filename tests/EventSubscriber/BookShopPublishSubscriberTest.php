<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\EventSubscriber;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\EventSubscriber\BookShopPublishSubscriber;
use c975L\BookBundle\Service\BookShopPublisher;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BookShopPublishSubscriberTest extends TestCase
{
    public function testABookSavedIsWrittenIntoTheShop(): void
    {
        $book = new Book();
        $publisher = $this->createMock(BookShopPublisher::class);
        $publisher->method('isAvailable')->willReturn(true);
        $publisher->expects($this->exactly(2))->method('publish')->with($book);

        $subscriber = new BookShopPublishSubscriber($publisher, $this->createStub(LoggerInterface::class));
        $subscriber->publish(new AfterEntityPersistedEvent($book));
        $subscriber->publish(new AfterEntityUpdatedEvent($book));
    }

    // Neither another entity nor a site without a shop
    public function testNothingIsWrittenForAnotherEntityOrWithoutAShop(): void
    {
        foreach ([[true, new Serie()], [false, new Book()]] as [$available, $entity]) {
            $publisher = $this->createMock(BookShopPublisher::class);
            $publisher->method('isAvailable')->willReturn($available);
            $publisher->expects($this->never())->method('publish');

            new BookShopPublishSubscriber($publisher, $this->createStub(LoggerInterface::class))->publish(new AfterEntityUpdatedEvent($entity));
        }
    }

    // The book is saved already: a shop failing is logged, never thrown at the editor
    public function testAShopFailingIsLogged(): void
    {
        $publisher = $this->createStub(BookShopPublisher::class);
        $publisher->method('isAvailable')->willReturn(true);
        $publisher->method('publish')->willThrowException(new \RuntimeException('disk full'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with($this->anything(), $this->callback(static fn (array $context): bool => 'disk full' === $context['message']));

        new BookShopPublishSubscriber($publisher, $logger)->publish(new AfterEntityUpdatedEvent(new Book()->setTitle('Le Loup')));
    }

    public function testItListensToTheTwoSaves(): void
    {
        $this->assertSame([AfterEntityPersistedEvent::class, AfterEntityUpdatedEvent::class], array_keys(BookShopPublishSubscriber::getSubscribedEvents()));
    }
}
