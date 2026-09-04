<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Listener;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\ContributorMedia;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\SerieMedia;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Entity\StripMedia;
use c975L\BookBundle\Listener\BookCacheInvalidationListener;
use c975L\BookBundle\Service\BookBlockCacheInvalidator;
use c975L\UiBundle\Entity\Block;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

// Which change of the catalog drops the cached blocks - UiBundle only ever invalidates the Block that was edited, and knows nothing of what these kinds query at render time
class BookCacheInvalidationListenerTest extends TestCase
{
    private array $invalidated;

    protected function setUp(): void
    {
        $this->invalidated = [];
    }

    // The four rows the five kinds are drawn from, each dropping the one catalog tag
    public function testACatalogChangeDropsTheBlocks(): void
    {
        foreach ([new Book(), new Serie(), new Strip(), new Contributor()] as $entity) {
            $this->invalidated = [];
            $this->listen($entity);

            $this->assertSame([[BookBlockCacheInvalidator::CACHE_TAG_CATALOG]], $this->invalidated, $entity::class);
        }
    }

    // Every Media of this bundle, whichever row it hangs on: a cover, a portrait and a planche's own file are all what a listing shows of what it lists
    public function testAnyMediaChangeDropsTheBlocks(): void
    {
        foreach ([new BookMedia(), new SerieMedia(), new StripMedia(), new ContributorMedia()] as $media) {
            $this->invalidated = [];
            $this->listen($media);

            $this->assertSame([[BookBlockCacheInvalidator::CACHE_TAG_CATALOG]], $this->invalidated, $media::class);
        }
    }

    // Every entity of the site travels through these events, and the catalog is not concerned by most of them
    public function testAnEntityOfAnotherBundleDropsNothing(): void
    {
        $this->listen(new Block());

        $this->assertSame([], $this->invalidated);
    }

    // A book added to an already-cached listing is an INSERT, for which postUpdate never fires
    public function testTheThreeEventsAllInvalidate(): void
    {
        $listener = $this->createListener();
        $manager = $this->createStub(EntityManagerInterface::class);
        $book = new Book();

        $listener->postPersist(new PostPersistEventArgs($book, $manager));
        $listener->postUpdate(new PostUpdateEventArgs($book, $manager));
        $listener->preRemove(new PreRemoveEventArgs($book, $manager));

        $this->assertCount(3, $this->invalidated);
    }

    private function listen(object $entity): void
    {
        $this->createListener()->postUpdate(new PostUpdateEventArgs($entity, $this->createStub(EntityManagerInterface::class)));
    }

    private function createListener(): BookCacheInvalidationListener
    {
        $cache = $this->createStub(TagAwareCacheInterface::class);
        $cache->method('invalidateTags')->willReturnCallback(function (array $tags): bool {
            $this->invalidated[] = $tags;

            return true;
        });

        return new BookCacheInvalidationListener(new BookBlockCacheInvalidator($cache));
    }
}
