<?php

namespace c975L\BookBundle\Listener;

use c975L\BookBundle\Entity\Book;
use DateTimeImmutable;
use Doctrine\ORM\Events;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Symfony\Bundle\SecurityBundle\Security;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;

#[AsEntityListener(event: Events::preFlush, method: 'preFlush', entity: Book::class)]
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: Book::class)]
class BookListener
{
    // public function __construct(
    //     private readonly Security $security,
    //     private readonly EntityManagerInterface $entityManager,
    // ) {
    // }

    public function preFlush(Book $entity, PreFlushEventArgs $event): void
    {
        $entity->setModification(new DateTimeImmutable());
    }

    public function prePersist(Book $entity, PrePersistEventArgs $event): void
    {
        $entity->setCreation(new DateTimeImmutable());
    }
}
