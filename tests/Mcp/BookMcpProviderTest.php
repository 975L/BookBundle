<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Mcp;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Mcp\BookMcpProvider;
use c975L\BookBundle\Repository\BookRepository;
use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Server\Exception\ToolCallException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\MockClock;

class BookMcpProviderTest extends TestCase
{
    private UserInterface $user;

    protected function setUp(): void
    {
        $this->user = $this->createStub(UserInterface::class);
    }

    // The tools named under the "book" key, the writing ones flagged
    public function testTools(): void
    {
        $tools = iterator_to_array($this->provider()->getMcpTools(), false);

        $this->assertSame('book', $this->provider()->getMcpKey());
        $this->assertStringContainsString('at once', $this->provider()->getMcpInstructions());
        $this->assertSame(['book_list', 'book_get', 'book_create', 'book_edit'], array_map(static fn ($tool): string => $tool->name, $tools));
        $this->assertSame([false, false, true, true], array_map(static fn ($tool): bool => $tool->writes, $tools));
    }

    // A draft added with a slug kept unique, no publication day, an empty title refused
    public function testCreateDraft(): void
    {
        $repository = $this->createStub(BookRepository::class);
        $repository->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?Book => \in_array($criteria['slug'], ['item-1', 'book'], true) ? new Book() : null);
        $persisted = [];
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $this->assertSame('item-1-2', $this->provider($repository, $entityManager)->create('Item 1', ' summary-1 ')['slug']);
        $this->assertInstanceOf(Book::class, $persisted[0]);
        $this->assertNull($persisted[0]->getPublished());
        $this->assertSame('summary-1', $persisted[0]->getSummary());
        $this->assertSame($this->user, $persisted[0]->getUser());

        // A title giving no ascii letter falls back on "book", numbered like any other
        $this->assertSame('book-2', $this->provider($repository, $entityManager)->create('!!!')['slug']);

        $this->expectException(ToolCallException::class);
        $this->provider($repository, $entityManager)->create(' ');
    }

    // Only the fields given change, a book in the trash answering as unknown
    public function testEdit(): void
    {
        $book = new Book()->setTitle('item-1')->setSummary('summary-1');
        $repository = $this->createStub(BookRepository::class);
        $repository->method('find')->willReturn($book);

        $this->provider($repository)->edit(1, summary: 'summary-2');
        $this->assertSame(['item-1', 'summary-2'], [$book->getTitle(), $book->getSummary()]);
        $this->assertSame($this->user, $book->getUser());

        $book->setIsDeleted(true);
        $this->expectException(ToolCallException::class);
        $this->provider($repository)->edit(1, title: 'item-2');
    }

    // An account that does not edit the catalog is refused every tool
    public function testRefusedWithoutTheRole(): void
    {
        $this->expectException(ToolCallException::class);
        $this->provider(granted: false)->list();
    }

    // The provider for an account editing the catalog or not
    private function provider(?BookRepository $repository = null, ?EntityManagerInterface $entityManager = null, bool $granted = true): BookMcpProvider
    {
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturn('ROLE_EDITOR');
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($granted);
        $security->method('getUser')->willReturn($this->user);

        return new BookMcpProvider($repository ?? $this->createStub(BookRepository::class), $entityManager ?? $this->createStub(EntityManagerInterface::class), $config, $security, new MockClock('2026-11-21 10:00'));
    }
}
