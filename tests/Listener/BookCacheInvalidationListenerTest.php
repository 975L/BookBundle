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
use c975L\BookBundle\Entity\BookCategory;
use c975L\BookBundle\Entity\BookContributor;
use c975L\BookBundle\Entity\BookEdition;
use c975L\BookBundle\Entity\BookLink;
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Entity\Character;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\ContributorLink;
use c975L\BookBundle\Entity\ContributorMedia;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\SerieMedia;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Entity\StripMedia;
use c975L\BookBundle\Listener\BookCacheInvalidationListener;
use c975L\BookBundle\Service\BookBlockCacheInvalidator;
use c975L\ConfigBundle\Entity\Config;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Translation;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;
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

    // The four rows the five kinds are drawn from, each dropping the one catalog tag - and an edition, whose channels decide what the Google feed lists
    public function testACatalogChangeDropsTheBlocks(): void
    {
        foreach ([new Book(), new Serie(), new Strip(), new Contributor(), new BookEdition()] as $entity) {
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

    // What a book's page alone prints, its sections being held under the same tag: its links, its credits, its characters, and the links of the people it credits
    public function testWhatABooksSectionsPrintDropsTheBlocks(): void
    {
        foreach ([new BookLink(), new BookContributor(), new Character(), new ContributorLink()] as $entity) {
            $this->invalidated = [];
            $this->listen($entity);

            $this->assertSame([[BookBlockCacheInvalidator::CACHE_TAG_CATALOG]], $this->invalidated, $entity::class);
        }
    }

    // A family of pages turned on or off changes the cached menu entries, and any book setting what a section says; a setting of the site's leaves the catalog alone
    public function testOnlyABookSettingDropsTheBlocks(): void
    {
        $this->listen(new Config()->setSlug('book-route-strips'));
        $this->assertSame([[BookBlockCacheInvalidator::CACHE_TAG_CATALOG]], $this->invalidated);

        $this->invalidated = [];
        $this->listen(new Config()->setSlug('book-plays-minimum'));
        $this->assertSame([[BookBlockCacheInvalidator::CACHE_TAG_CATALOG]], $this->invalidated);

        $this->invalidated = [];
        $this->listen(new Config()->setSlug('site-name'));
        $this->assertSame([], $this->invalidated);
    }

    // The catalog's own translations are what its sections print in another language; another bundle's leave it alone
    public function testOnlyACatalogTranslationDropsTheBlocks(): void
    {
        $this->listen(new Translation('book_media', 3, 'title', 'en'));
        $this->assertSame([[BookBlockCacheInvalidator::CACHE_TAG_CATALOG]], $this->invalidated);

        $this->invalidated = [];
        $this->listen(new Translation('ui_block', 3, 'title', 'en'));
        $this->assertSame([], $this->invalidated);
    }

    // A book added to an already-cached listing is an INSERT, for which postUpdate never fires; and however many rows a flush wrote, the tag is dropped once
    public function testTheThreeEventsInvalidateOncePerFlush(): void
    {
        $listener = $this->createListener();
        $manager = $this->createStub(EntityManagerInterface::class);
        $book = new Book();

        $listener->postPersist(new PostPersistEventArgs($book, $manager));
        $listener->postUpdate(new PostUpdateEventArgs(new BookLink(), $manager));
        $listener->preRemove(new PreRemoveEventArgs(new BookContributor(), $manager));
        $this->assertSame([], $this->invalidated);

        $listener->postFlush(new PostFlushEventArgs($manager));
        $listener->postFlush(new PostFlushEventArgs($manager));
        $this->assertCount(1, $this->invalidated);
    }

    // A category added to a book and nothing else: no changeset on the book, only its collection scheduled
    public function testACollectionChangedAloneDropsTheBlocks(): void
    {
        $collection = new PersistentCollection($this->createStub(EntityManagerInterface::class), new ClassMetadata(BookCategory::class), new ArrayCollection());
        new \ReflectionProperty(PersistentCollection::class, 'owner')->setValue($collection, new Book());

        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getScheduledCollectionUpdates')->willReturn([$collection]);
        $unitOfWork->method('getScheduledCollectionDeletions')->willReturn([]);
        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('getUnitOfWork')->willReturn($unitOfWork);

        $listener = $this->createListener();
        $listener->onFlush(new OnFlushEventArgs($manager));
        $listener->postFlush(new PostFlushEventArgs($manager));

        $this->assertSame([[BookBlockCacheInvalidator::CACHE_TAG_CATALOG]], $this->invalidated);
    }

    private function listen(object $entity): void
    {
        $manager = $this->createStub(EntityManagerInterface::class);
        $listener = $this->createListener();
        $listener->postUpdate(new PostUpdateEventArgs($entity, $manager));
        $listener->postFlush(new PostFlushEventArgs($manager));
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
