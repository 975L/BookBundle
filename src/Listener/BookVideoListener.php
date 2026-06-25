<?php

namespace c975L\BookBundle\Listener;

use Doctrine\ORM\Events;
use c975L\BookBundle\Entity\BookVideo;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;

#[AsEntityListener(event: Events::preFlush, method: 'preFlush', entity: BookVideo::class)]
class BookVideoListener
{
    public function preFlush(BookVideo $entity, PreFlushEventArgs $event): void
    {
        if (null === $entity->getPosition()) {
            $maxPosition = 0;
            foreach ($entity->getBook()->getVideos() as $video) {
                $maxPosition = max($maxPosition, $video->getPosition());
            }
            $entity->setPosition($maxPosition + 5);
        }
    }
}
