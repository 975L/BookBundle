<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Service;

use c975L\BookBundle\Contract\BookCatalogProviderInterface;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Service\BookCatalogRegistry;
use c975L\BookBundle\Service\BookService;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Service\Paginator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

// What every public read of a book goes through: the repository's own answers, and the site's own catalog where it declares one
class BookServiceTest extends TestCase
{
    /** @return list<Book> */
    private function books(int $count, string $prefix = 'livre'): array
    {
        return array_map(static fn (int $i) => new Book()->setTitle(ucfirst($prefix) . ' ' . $i)->setSlug($prefix . '-' . $i), range(1, $count));
    }

    private function createService(BookRepository $repository, array $providers = []): BookService
    {
        $requestStack = new RequestStack([new Request()]);

        return new BookService(new Paginator($requestStack), $repository, new BookCatalogRegistry($providers, $this->createStub(ConfigServiceInterface::class)));
    }

    private function repository(array $published = []): BookRepository
    {
        $repository = $this->createStub(BookRepository::class);
        $repository->method('findAllPublished')->willReturn($published);

        return $repository;
    }

    // A catalog published in editions does not say "out" the way a book with a single date does, so the site's own list wins
    public function testASiteDeclaringItsOwnCatalogIsListedByIt(): void
    {
        $own = $this->books(3, 'sien');

        $provider = $this->createStub(BookCatalogProviderInterface::class);
        $provider->method('getBooks')->willReturn($own);

        $pagination = $this->createService($this->repository($this->books(12)), [$provider])
            ->findAllPaginated(new InputBag())
        ;

        $this->assertSame($own, iterator_to_array($pagination));
    }

    // Declaring nothing is every site not naming its own catalog: the bundle's own published read answers
    public function testASiteDeclaringNothingIsListedByTheBundlesOwnRead(): void
    {
        $published = $this->books(12);

        $pagination = $this->createService($this->repository($published))->findAllPaginated(new InputBag());

        $this->assertCount(10, iterator_to_array($pagination));
        $this->assertSame(12, $pagination->getTotalItemCount());
    }

    // A provider answering null hands the question on rather than emptying the catalog
    public function testAProviderAnsweringNothingHandsTheQuestionOn(): void
    {
        $provider = $this->createStub(BookCatalogProviderInterface::class);
        $provider->method('getBooks')->willReturn(null);

        $pagination = $this->createService($this->repository($this->books(4)), [$provider])
            ->findAllPaginated(new InputBag())
        ;

        $this->assertCount(4, iterator_to_array($pagination));
    }

    public function testTheIndexIsCutTenPerPage(): void
    {
        $pagination = $this->createService($this->repository($this->books(23)))
            ->findAllPaginated(new InputBag([Paginator::PAGE_PARAMETER => '3']))
        ;

        $this->assertCount(3, iterator_to_array($pagination));
        $this->assertSame(3, $pagination->getCurrentPageNumber());
    }

    // The slug is what a book's page asks for before falling back on the number
    public function testABookIsFoundByItsSlug(): void
    {
        $book = new Book()->setSlug('le-fil-rouge');

        $repository = $this->createMock(BookRepository::class);
        $repository->expects($this->once())->method('findOneBy')->with(['slug' => 'le-fil-rouge'])->willReturn($book);

        $this->assertSame($book, $this->createService($repository)->findOneBySlug('le-fil-rouge'));
    }

    // Every other read is the repository's own answer, handed over untouched
    public function testTheOtherReadsAreTheRepositorysOwn(): void
    {
        $online = $this->books(2, 'en-ligne');
        $toBePublished = $this->books(1, 'a-paraitre');
        $found = $this->books(1, 'trouve');

        $repository = $this->createStub(BookRepository::class);
        $repository->method('findAllOnline')->willReturn($online);
        $repository->method('findAllToBePublished')->willReturn($toBePublished);
        $repository->method('search')->willReturn($found);
        $repository->method('countPublished')->willReturn(17);
        $repository->method('findLanguages')->willReturn(['fr', 'en']);

        $service = $this->createService($repository);

        $this->assertSame($online, $service->findAllOnline());
        $this->assertSame($toBePublished, $service->findAllToBePublished());
        $this->assertSame($found, $service->search('fil'));
        $this->assertSame(17, $service->countPublished());
        $this->assertSame(['fr', 'en'], $service->findLanguages());
    }
}
