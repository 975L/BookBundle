<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Listener;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookCategory;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Media;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Service\BookBlockCacheInvalidator;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;

// Drops the cached renders of this bundle's blocks whenever the catalog they read changes - a book published, a serie renamed, a planche put online, a person credited, a cover replaced - postPersist as much as postUpdate, a book added to a cached listing being an INSERT
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
class BookCacheInvalidationListener
{
    public function __construct(private readonly BookBlockCacheInvalidator $invalidator)
    {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->invalidate($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->invalidate($args->getObject());
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $this->invalidate($args->getObject());
    }

    // Media covers the covers, the portraits and the planches' own files alike, every one of them being a subclass of it (see Entity\Media): a cover replaced changes every listing showing it
    private function invalidate(object $entity): void
    {
        match (true) {
            $entity instanceof Book,
            $entity instanceof BookCategory,
            $entity instanceof Serie,
            $entity instanceof Strip,
            $entity instanceof Contributor,
            $entity instanceof Media => $this->invalidator->invalidateCatalog(),
            default => null,
        };
    }
}
