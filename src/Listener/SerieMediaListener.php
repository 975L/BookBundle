<?php

namespace c975L\BookBundle\Listener;

use Doctrine\ORM\Events;
use c975L\BookBundle\Entity\SerieMedia;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;

#[AsEntityListener(event: Events::preFlush, method: 'preFlush', entity: SerieMedia::class)]
class SerieMediaListener
{
    public function preFlush(SerieMedia $entity, PreFlushEventArgs $event): void
    {
        if (null === $entity->getPosition()) {
            $maxPosition = 0;
            foreach ($entity->getSerie()->getMedias() as $media) {
                $maxPosition = max($maxPosition, $media->getPosition());
            }
            $entity->setPosition($maxPosition + 5);
        }
    }
}
