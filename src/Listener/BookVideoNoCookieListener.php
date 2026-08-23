<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Listener;

use c975L\BookBundle\Entity\Media;
use c975L\UiBundle\Twig\VideoExtension;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

// A video's address is brought back to its platform's canonical form once, before reaching the database - what UiBundle does for its video blocks, with the same checkbox and the same rendering that never has to care (see c975L\UiBundle\Listener\BlockVideoNoCookieListener)
// Whatever form was pasted - the address bar, a share link, a player address - what is stored is the address the site's security policy allows, and it is the one the template hands to the iframe
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
class BookVideoNoCookieListener
{
    public function __construct(private readonly VideoExtension $videoExtension)
    {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->rewriteUrl($args->getObject());
    }

    // Doctrine has already taken the changeset by then: without this recomputation the rewritten address would simply be ignored
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$this->rewriteUrl($entity)) {
            return;
        }

        $entityManager = $args->getObjectManager();
        $entityManager->getUnitOfWork()->recomputeSingleEntityChangeSet(
            $entityManager->getClassMetadata($entity::class),
            $entity
        );
    }

    // Rewrites the address in place when the box is ticked and the platform is recognized, and says whether it did
    private function rewriteUrl(object $entity): bool
    {
        if (!$entity instanceof Media || !$entity->isNoCookie()) {
            return false;
        }

        $url = (string) $entity->getYoutubeUrl();
        if ('' === $url) {
            return false;
        }

        $rewritten = $this->videoExtension->toPrivacyEmbedUrl($url);
        if ($rewritten === $url) {
            return false;
        }

        $entity->setYoutubeUrl($rewritten);

        return true;
    }
}
