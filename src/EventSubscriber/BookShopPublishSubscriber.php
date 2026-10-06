<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\EventSubscriber;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Service\BookShopPublisher;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

// A book saved in the back-office is written into the shop straight after (see BookShopPublisher) - once saved, never before: the files have to be on disk for the shop to copy them. A shop that fails is logged rather than thrown, the book itself being saved already
class BookShopPublishSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly BookShopPublisher $publisher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterEntityPersistedEvent::class => 'publish',
            AfterEntityUpdatedEvent::class => 'publish',
        ];
    }

    public function publish(AfterEntityPersistedEvent | AfterEntityUpdatedEvent $event): void
    {
        $book = $event->getEntityInstance();
        if (!$book instanceof Book || !$this->publisher->isAvailable()) {
            return;
        }

        try {
            $this->publisher->publish($book);
        } catch (\Throwable $exception) {
            $this->logger->error('The book "{title}" could not be written into the shop: {message}', ['title' => $book->getTitle(), 'message' => $exception->getMessage()]);
        }
    }
}
