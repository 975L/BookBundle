<?php

namespace c975L\BookBundle\Listener;

use Doctrine\ORM\Events;
use c975L\BookBundle\Entity\BookPresse;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;

#[AsEntityListener(event: Events::preFlush, method: 'preFlush', entity: BookPresse::class)]
class BookPresseListener
{
    public function preFlush(BookPresse $entity, PreFlushEventArgs $event): void
    {
        if (null === $entity->getPosition()) {
            $maxPosition = 0;
            foreach ($entity->getBook()->getPresses() as $presse) {
                $maxPosition = max($maxPosition, $presse->getPosition());
            }
            $entity->setPosition($maxPosition + 5);
        }
    }
}
