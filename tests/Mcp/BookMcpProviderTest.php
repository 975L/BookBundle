<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Mcp;

use c975L\BookBundle\Command\BookPublishCommand;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Mcp\BookMcpProvider;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Repository\SerieRepository;
use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Server\Exception\ToolCallException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem;

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

    // A draft placed by its number, language and serie, linked both ways to the book it translates
    public function testCreateTranslation(): void
    {
        $source = new Book()->setTitle('item-1')->setLanguage('fr');
        $serie = new Serie();
        $repository = $this->createStub(BookRepository::class);
        $repository->method('find')->willReturn($source);
        $series = $this->createStub(SerieRepository::class);
        $series->method('find')->willReturn($serie);
        $persisted = [];
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $this->provider($repository, $entityManager, series: $series)->create('item-2', number: 2, language: 'en', serie: 1, translates: 1);
        $book = $persisted[0];
        $this->assertSame([2, 'en', $serie, $source, $book], [$book->getNumber(), $book->getLanguage(), $book->getSerie(), $book->getTranslationBook(), $source->getTranslationBook()]);
        $this->assertNull($book->getPublished());
        $read = $this->provider($this->repositoryFinding($book))->get(1);
        $this->assertSame(['en', null, null], [$read['language'], $read['serieId'], $read['translates']]);

        // A book already naming its translation keeps it
        $other = new Book();
        $source->setTranslationBook($other);
        $this->provider($repository, $entityManager, series: $series)->create('item-3', translates: 1);
        $this->assertSame($other, $source->getTranslationBook());
    }

    // A language the back office does not offer is refused
    public function testCreateRefusesAnUnknownLanguage(): void
    {
        $this->expectException(ToolCallException::class);
        $this->provider()->create('item-1', language: 'xx');
    }

    // An unknown serie is refused
    public function testCreateRefusesAnUnknownSerie(): void
    {
        $this->expectException(ToolCallException::class);
        $this->provider()->create('item-1', serie: 1);
    }

    // A serie in the trash is refused as unknown
    public function testCreateRefusesASerieInTheTrash(): void
    {
        $series = $this->createStub(SerieRepository::class);
        $series->method('find')->willReturn(new Serie()->setIsDeleted(true));

        $this->expectException(ToolCallException::class);
        $this->provider(series: $series)->create('item-1', serie: 1);
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

    // A book placed again by its number, language and serie, linked both ways to the book it translates, what is not given kept
    public function testEditPlacesTheBook(): void
    {
        $serie = new Serie();
        $book = new Book()->setTitle('item-1')->setNumber(1)->setLanguage('fr');
        $source = new Book()->setTitle('item-0');
        $repository = $this->createStub(BookRepository::class);
        $repository->method('find')->willReturnCallback(static fn (int $id): Book => 1 === $id ? $book : $source);
        $series = $this->createStub(SerieRepository::class);
        $series->method('find')->willReturn($serie);

        $this->provider($repository, series: $series)->edit(1, language: 'en', serie: 3, translates: 2);
        $this->assertSame([1, 'en', $serie, $source, $book], [$book->getNumber(), $book->getLanguage(), $book->getSerie(), $book->getTranslationBook(), $source->getTranslationBook()]);
        $this->provider($repository, series: $series)->edit(1, number: 2);
        $this->assertSame([2, 'en', $serie], [$book->getNumber(), $book->getLanguage(), $book->getSerie()]);
    }

    // A language the back office does not offer or a book translating itself is refused
    public function testEditRefusesWhatTheCatalogCannotHold(): void
    {
        $repository = $this->repositoryFinding(new Book()->setTitle('item-1'));
        try {
            $this->provider($repository)->edit(1, language: 'xx');
            $this->fail('An unknown language is refused.');
        } catch (ToolCallException) {
        }

        $this->expectException(ToolCallException::class);
        $this->provider($repository)->edit(1, translates: 1);
    }

    // An account that does not edit the catalog is refused every tool
    public function testRefusedWithoutTheRole(): void
    {
        $this->expectException(ToolCallException::class);
        $this->provider(granted: false)->list();
    }

    // A repository finding the given book by any id
    private function repositoryFinding(Book $book): BookRepository
    {
        $repository = $this->createStub(BookRepository::class);
        $repository->method('find')->willReturn($book);

        return $repository;
    }

    // The provider for an account editing the catalog or not
    private function provider(?BookRepository $repository = null, ?EntityManagerInterface $entityManager = null, bool $granted = true, ?SerieRepository $series = null, string $projectDir = '/nowhere'): BookMcpProvider
    {
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturn('ROLE_EDITOR');
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($granted);
        $security->method('getUser')->willReturn($this->user);

        return new BookMcpProvider($repository ?? $this->createStub(BookRepository::class), $series ?? $this->createStub(SerieRepository::class), $entityManager ?? $this->createStub(EntityManagerInterface::class), $config, $security, new MockClock('2026-11-21 10:00'), $projectDir);
    }

    // A new version kept for its day is read with the book, none otherwise
    public function testGetTellsTheNewVersionWaiting(): void
    {
        $directory = sys_get_temp_dir() . '/book-mcp-' . uniqid();
        $book = new Book()->setTitle('item-1')->setSlug('item-1');
        $this->assertNull($this->provider($this->repositoryFinding($book), projectDir: $directory)->get(1)['newVersionOn']);

        new Filesystem()->dumpFile($directory . '/' . BookPublishCommand::VERSIONS_DIRECTORY . '/item-1/manifest.json', '{"published": "2026-12-05"}');
        $this->assertSame('2026-12-05', $this->provider($this->repositoryFinding($book), projectDir: $directory)->get(1)['newVersionOn']);
        new Filesystem()->remove($directory);
    }
}
