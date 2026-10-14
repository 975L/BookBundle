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
use c975L\BookBundle\Entity\BookContributor;
use c975L\BookBundle\Entity\BookEdition;
use c975L\BookBundle\Entity\BookLink;
use c975L\BookBundle\Entity\Character;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\ContributorLink;
use c975L\BookBundle\Entity\Media;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Service\BookBlockCacheInvalidator;
use c975L\ConfigBundle\Entity\Config;
use c975L\UiBundle\Entity\Translation;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;

// Drops the cached renders of this bundle's blocks whenever the catalog they read changes - a book published, a serie renamed, a planche put online, a person credited, a cover replaced, an edition's channels ticked (see GooglePlayFeed::ebooks()) - postPersist as much as postUpdate, a book added to a cached listing being an INSERT. Each event only marks the catalog changed, the tag being dropped once at postFlush: a book saved with its links and credits is one flush, not one invalidation per row
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
class BookCacheInvalidationListener
{
    private bool $changed = false;

    public function __construct(private readonly BookBlockCacheInvalidator $invalidator)
    {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->mark($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->mark($args->getObject());
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $this->mark($args->getObject());
    }

    // A collection changed alone - a category added to a book, nothing else touched - leaves its owner without a changeset, so no postUpdate ever fires for it
    public function onFlush(OnFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();
        foreach ([...$unitOfWork->getScheduledCollectionUpdates(), ...$unitOfWork->getScheduledCollectionDeletions()] as $collection) {
            if (null !== $collection->getOwner()) {
                $this->mark($collection->getOwner());
            }
        }
    }

    // Once per flush, whatever the number of rows it wrote
    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->changed) {
            $this->changed = false;
            $this->invalidator->invalidateCatalog();
        }
    }

    // Media covers the covers, the portraits and the planches' own files alike, every one of them being a subclass of it (see Entity\Media): a cover replaced changes every listing showing it. The rows a book's page alone prints count too, its sections being held under the same tag (see book/_sections.html.twig): its links, its credits, its characters, and the translations of the catalog (see BookTranslator, every owner type of which starts with "book_"). A "book-*" setting turns a family of pages on or off, which the cached menu entries depend on (see Management\LinkableRouteProvider), or changes what a section says
    private function mark(object $entity): void
    {
        $this->changed = $this->changed || match (true) {
            $entity instanceof Book,
            $entity instanceof BookEdition,
            $entity instanceof BookCategory,
            $entity instanceof BookLink,
            $entity instanceof BookContributor,
            $entity instanceof Character,
            $entity instanceof Serie,
            $entity instanceof Strip,
            $entity instanceof Contributor,
            $entity instanceof ContributorLink,
            $entity instanceof Media,
            $entity instanceof Translation && str_starts_with($entity->getOwnerType(), 'book_'),
            $entity instanceof Config && str_starts_with($entity->getSlug(), 'book-') => true,
            default => false,
        };
    }
}
