<?php

namespace c975L\BookBundle\Listener;

use c975L\BookBundle\Entity\Serie;
use DateTimeImmutable;
use Doctrine\ORM\Events;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Symfony\Bundle\SecurityBundle\Security;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;

#[AsEntityListener(event: Events::preFlush, method: 'preFlush', entity: Serie::class)]
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: Serie::class)]
class SerieListener
{
    // public function __construct(
    //     private readonly Security $security,
    //     private readonly EntityManagerInterface $entityManager,
    // ) {
    // }

    public function preFlush(Serie $entity, PreFlushEventArgs $event): void
    {
        $entity->setModification(new DateTimeImmutable());
    }

    public function prePersist(Serie $entity, PrePersistEventArgs $event): void
    {
        $entity->setCreation(new DateTimeImmutable());
    }
}
