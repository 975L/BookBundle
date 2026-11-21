<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Mcp;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Repository\BookRepository;
use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Mcp\McpTool;
use c975L\ConfigBundle\Mcp\McpToolProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Server\Exception\ToolCallException;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\String\Slugger\AsciiSlugger;

// The catalog's books on the site's MCP endpoint, for an account that edits them on the back office: listed, read, a draft added and its card written, publishing left to a human
class BookMcpProvider implements McpToolProviderInterface
{
    // Books a list gives at most
    public const int LIMIT = 50;

    public function __construct(
        private readonly BookRepository $bookRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ConfigServiceInterface $configService,
        private readonly Security $security,
        private readonly ClockInterface $clock,
    ) {
    }

    public function getMcpKey(): string
    {
        return 'book';
    }

    public function getMcpInstructions(): string
    {
        return 'Books are the catalog of the site. A book added by an agent is a draft, without a publication day: a human publishes it from the back office.';
    }

    public function getMcpTools(): iterable
    {
        yield new McpTool('book_list', 'Books', 'The books whose title holds the search words, every book without them, drafts included, the latest first.', $this->list(...));
        yield new McpTool('book_get', 'Book', 'A book: title, summary, number, serie, publication day (none for a draft).', $this->get(...));
        yield new McpTool('book_create', 'Add a draft book', 'Adds a book as a draft, with its title and an optional summary.', $this->create(...), true);
        yield new McpTool('book_edit', 'Write a book', 'Changes only the title or summary given of a book.', $this->edit(...), true);
    }

    // The books whose title holds the words, every one without them, the trash left aside
    /**
     * @param ?string $search words looked for in the titles
     *
     * @return list<array{id: ?int, title: ?string, number: ?int, serie: ?string, published: ?string}>
     */
    public function list(?string $search = null): array
    {
        $this->guard();
        $builder = $this->bookRepository->createQueryBuilder('b')
            ->andWhere('b.isDeleted = false')
            ->orderBy('b.creation', \SortDirection::Descending)
            ->setMaxResults(self::LIMIT);
        if ('' !== trim((string) $search)) {
            $builder->andWhere('b.title LIKE :words')->setParameter('words', '%' . addcslashes(trim((string) $search), '%_') . '%');
        }

        return array_map($this->summary(...), $builder->getQuery()->getResult());
    }

    // A book with its summary
    /**
     * @param int $book id of the book
     *
     * @return array{id: ?int, title: ?string, number: ?int, serie: ?string, published: ?string, slug: ?string, summary: ?string}
     */
    public function get(int $book): array
    {
        $found = $this->found($book);

        return [...$this->summary($found), 'slug' => $found->getSlug(), 'summary' => $found->getSummary()];
    }

    // A draft book added, its slug made from its title and kept unique by a number
    /**
     * @param string  $title   title of the book
     * @param ?string $summary its summary, html allowed
     *
     * @return array{id: ?int, slug: ?string}
     */
    public function create(string $title, ?string $summary = null): array
    {
        $this->guard();
        $title = mb_substr(trim($title), 0, 100);
        if ('' === $title) {
            throw new ToolCallException('The title is empty.');
        }
        $now = $this->clock->now();
        $user = $this->security->getUser();
        $book = new Book()
            ->setTitle($title)
            ->setSlug($this->slug($title))
            ->setSummary('' === trim((string) $summary) ? null : trim((string) $summary))
            ->setCreation($now)
            ->setModification($now)
            ->setUser($user instanceof UserInterface ? $user : null);
        $this->entityManager->persist($book);
        $this->entityManager->flush();

        return ['id' => $book->getId(), 'slug' => $book->getSlug()];
    }

    // A book's title or summary changed, only those given, an empty summary clearing it
    /**
     * @param int     $book    id of the book
     * @param ?string $title   new title
     * @param ?string $summary new summary, html allowed, empty to clear it
     *
     * @return array{id: ?int}
     */
    public function edit(int $book, ?string $title = null, ?string $summary = null): array
    {
        $found = $this->found($book);
        if ('' !== trim((string) $title)) {
            $found->setTitle(mb_substr(trim((string) $title), 0, 100));
        }
        if (null !== $summary) {
            $found->setSummary('' === trim($summary) ? null : trim($summary));
        }
        $found->setModification($this->clock->now());
        $this->entityManager->flush();

        return ['id' => $found->getId()];
    }

    // The catalog is the editor's business, as on the back office
    private function guard(): void
    {
        if (!$this->security->isGranted((string) $this->configService->get('site-role-editor'))) {
            throw new ToolCallException('This account may not work on the books.');
        }
    }

    // A book by its id, one in the trash answering as unknown
    private function found(int $id): Book
    {
        $this->guard();
        $book = $this->bookRepository->find($id);
        if (!$book instanceof Book || $book->isDeleted()) {
            throw new ToolCallException(sprintf('No book %d.', $id));
        }

        return $book;
    }

    // A book in a list
    /** @return array{id: ?int, title: ?string, number: ?int, serie: ?string, published: ?string} */
    private function summary(Book $book): array
    {
        return [
            'id' => $book->getId(),
            'title' => $book->getTitle(),
            'number' => $book->getNumber(),
            'serie' => $book->getSerie()?->getTitle(),
            'published' => $book->getPublished()?->format('Y-m-d'),
        ];
    }

    // A slug from the title, a number added while another book holds it
    private function slug(string $title): string
    {
        $base = mb_substr(strtolower(new AsciiSlugger()->slug($title)->toString()), 0, 90);
        $root = '' === $base ? 'book' : $base;
        $slug = $root;
        $number = 2;
        while (null !== $this->bookRepository->findOneBy(['slug' => $slug])) {
            $slug = $root . '-' . $number++;
        }

        return $slug;
    }
}
