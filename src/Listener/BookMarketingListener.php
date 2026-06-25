<?php

namespace c975L\BookBundle\Listener;

use Doctrine\ORM\Events;
use c975L\BookBundle\Entity\BookMarketing;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;

#[AsEntityListener(event: Events::preFlush, method: 'preFlush', entity: BookMarketing::class)]
class BookMarketingListener
{
    public function preFlush(BookMarketing $entity, PreFlushEventArgs $event): void
    {
        if (null === $entity->getPosition()) {
            $maxPosition = 0;
            foreach ($entity->getBook()->getMarketings() as $marketing) {
                $maxPosition = max($maxPosition, $marketing->getPosition());
            }
            $entity->setPosition($maxPosition + 5);
        }
    }
}
