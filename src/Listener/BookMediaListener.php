<?php

namespace c975L\BookBundle\Listener;

use Doctrine\ORM\Events;
use c975L\BookBundle\Entity\BookMedia;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;

#[AsEntityListener(event: Events::preFlush, method: 'preFlush', entity: BookMedia::class)]
class BookMediaListener
{
    public function preFlush(BookMedia $entity, PreFlushEventArgs $event): void
    {
        if (null === $entity->getPosition()) {
            $maxPosition = 0;
            foreach ($entity->getBook()->getMedias() as $media) {
                $maxPosition = max($maxPosition, $media->getPosition());
            }
            $entity->setPosition($maxPosition + 5);
        }
    }
}
