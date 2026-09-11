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
use c975L\BookBundle\Listener\BookTranslationPurgeListener;
use c975L\BookBundle\Service\BookTranslator;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Repository\TranslationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use PHPUnit\Framework\TestCase;

// Translations name their owner rather than pointing at it, so no foreign key takes them along - a new row landing on a deleted one's id would otherwise inherit its translated title
class BookTranslationPurgeListenerTest extends TestCase
{
    // The id is read on preRemove: Doctrine has handed it back to null by the time postRemove is dispatched
    public function testABookTakesItsTranslationsWithIt(): void
    {
        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->once())
            ->method('deleteByOwner')
            ->with(BookTranslator::OWNER_BOOK, 3);

        $this->remove($repository, $this->createBook(3));
    }

    // A block is UiBundle's own listener's business, and would otherwise be purged twice under two owner types
    public function testARowOfAnotherKindIsLeftAlone(): void
    {
        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->never())->method('deleteByOwner');

        $this->remove($repository, new Block());
    }

    // A book that was never persisted owns no row keyed on an id it does not have
    public function testABookWithoutAnIdDeletesNothing(): void
    {
        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->never())->method('deleteByOwner');

        $this->remove($repository, $this->createBook(null));
    }

    // postRemove alone has no id left to go on, whatever the row still says: that is the very state Doctrine hands it
    public function testPostRemoveWithoutPreRemoveDeletesNothing(): void
    {
        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->never())->method('deleteByOwner');

        new BookTranslationPurgeListener($repository)->postRemove(new PostRemoveEventArgs($this->createBook(3), $this->createStub(EntityManagerInterface::class)));
    }

    // What Doctrine does with a removal: preRemove while the row still has its id, which it hands back to null before postRemove
    private function remove(TranslationRepository $repository, object $entity): void
    {
        $listener = new BookTranslationPurgeListener($repository);
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $listener->preRemove(new PreRemoveEventArgs($entity, $entityManager));
        new \ReflectionProperty($entity, 'id')->setValue($entity, null);
        $listener->postRemove(new PostRemoveEventArgs($entity, $entityManager));
    }

    private function createBook(?int $id): Book
    {
        $book = new Book();
        new \ReflectionProperty(Book::class, 'id')->setValue($book, $id);

        return $book;
    }
}
