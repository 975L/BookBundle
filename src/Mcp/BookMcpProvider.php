<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Mcp;

use c975L\BookBundle\Command\BookPublishCommand;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Repository\SerieRepository;
use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Mcp\McpTool;
use c975L\ConfigBundle\Mcp\McpToolProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Server\Exception\ToolCallException;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\AsciiSlugger;

// The catalog's books on the site's MCP endpoint, for an account that edits them on the back office: listed, read, a draft added and its card written, publishing left to a human
class BookMcpProvider implements McpToolProviderInterface
{
    // Books a list gives at most
    public const int LIMIT = 50;

    public function __construct(
        private readonly BookRepository $bookRepository,
        private readonly SerieRepository $serieRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ConfigServiceInterface $configService,
        private readonly Security $security,
        private readonly ClockInterface $clock,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    public function getMcpKey(): string
    {
        return 'book';
    }

    public function getMcpInstructions(): string
    {
        return 'Books are the catalog of the site. A book added by an agent is a draft, without a publication day: a human publishes it from the back office. An edit of a book the site already shows appears there at once.';
    }

    public function getMcpTools(): iterable
    {
        yield new McpTool('book_list', 'Books', 'The books whose title holds the search words, every book without them, drafts included, the latest first.', $this->list(...));
        yield new McpTool('book_get', 'Book', 'A book: title, summary, number, serie and its id, language, the book it translates, publication day (none for a draft) and the day a new version waits for, if any.', $this->get(...));
        yield new McpTool('book_create', 'Add a draft book', 'Adds a book as a draft, with its title and optionally its summary, number, language, serie and the book it translates, the two then linked both ways.', $this->create(...), true);
        yield new McpTool('book_edit', 'Write a book', 'Changes only the title, summary, number, language, serie or book it translates given of a book, seen at once when the site already shows it.', $this->edit(...), true);
    }

    // The books whose title holds the words, every one without them, the trash left aside
    /**
     * @param ?string $search words looked for in the titles
     *
     * @return list<array{id: ?int, title: ?string, number: ?int, serie: ?string, language: ?string, published: ?string}>
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
     * @return array{id: ?int, title: ?string, number: ?int, serie: ?string, language: ?string, published: ?string, slug: ?string, summary: ?string, serieId: ?int, translates: ?int, newVersionOn: ?string}
     */
    public function get(int $book): array
    {
        $found = $this->found($book);

        return [...$this->summary($found), 'slug' => $found->getSlug(), 'summary' => $found->getSummary(), 'serieId' => $found->getSerie()?->getId(), 'translates' => $found->getTranslationBook()?->getId(), 'newVersionOn' => $this->newVersionOn($found)];
    }

    // The day a new version of the book waits for under BookPublishCommand::VERSIONS_DIRECTORY, null when none waits
    private function newVersionOn(Book $book): ?string
    {
        $path = $this->projectDir . '/' . BookPublishCommand::VERSIONS_DIRECTORY . '/' . $book->getSlug() . '/manifest.json';
        $day = is_file($path) ? (json_decode((string) file_get_contents($path), true)['published'] ?? null) : null;

        return \is_string($day) ? $day : null;
    }

    // A draft book added, its slug made from its title and kept unique by a number, placed in the catalog by what is given
    /**
     * @param string  $title      title of the book
     * @param ?string $summary    its summary, html allowed
     * @param ?int    $number     its number, the rank in its serie
     * @param ?string $language   the language it is written in: fr, en or es
     * @param ?int    $serie      id of its serie
     * @param ?int    $translates id of the book it translates, linked both ways
     *
     * @return array{id: ?int, slug: ?string}
     */
    public function create(string $title, ?string $summary = null, ?int $number = null, ?string $language = null, ?int $serie = null, ?int $translates = null): array
    {
        $this->guard();
        $title = mb_substr(trim($title), 0, 100);
        if ('' === $title) {
            throw new ToolCallException('The title is empty.');
        }
        $this->checkLanguage($language);
        $source = null !== $translates ? $this->found($translates) : null;
        $now = $this->clock->now();
        $book = new Book()
            ->setTitle($title)
            ->setSlug($this->slug($title))
            ->setSummary('' === trim((string) $summary) ? null : trim((string) $summary))
            ->setCreation($now)
            ->setModification($now)
            ->setUser($this->currentUser());
        $this->place($book, $number, $language, null !== $serie ? $this->serie($serie) : null, $source);
        $this->entityManager->persist($book);
        $this->entityManager->flush();

        return ['id' => $book->getId(), 'slug' => $book->getSlug()];
    }

    // A book's title, summary, number, language, serie or translation changed, only those given, an empty summary clearing it, the account named as its last editor as on the back office
    /**
     * @param int     $book       id of the book
     * @param ?string $title      new title
     * @param ?string $summary    new summary, html allowed, empty to clear it
     * @param ?int    $number     new number, the rank in its serie
     * @param ?string $language   new language: fr, en or es
     * @param ?int    $serie      id of its new serie
     * @param ?int    $translates id of the book it translates, linked both ways
     *
     * @return array{id: ?int}
     */
    public function edit(int $book, ?string $title = null, ?string $summary = null, ?int $number = null, ?string $language = null, ?int $serie = null, ?int $translates = null): array
    {
        $found = $this->found($book);
        $this->checkLanguage($language);
        if ($translates === $book) {
            throw new ToolCallException('A book does not translate itself.');
        }
        $source = null !== $translates ? $this->found($translates) : null;
        if ('' !== trim((string) $title)) {
            $found->setTitle(mb_substr(trim((string) $title), 0, 100));
        }
        if (null !== $summary) {
            $found->setSummary('' === trim($summary) ? null : trim($summary));
        }
        $this->place($found, $number, $language, null !== $serie ? $this->serie($serie) : null, $source);
        $found->setModification($this->clock->now())->setUser($this->currentUser());
        $this->entityManager->flush();

        return ['id' => $found->getId()];
    }

    // The number, language, serie and translation given set on the book, those not given left as they were
    private function place(Book $book, ?int $number, ?string $language, ?Serie $serie, ?Book $source): void
    {
        $book
            ->setNumber($number ?? $book->getNumber())
            ->setLanguage($language ?? $book->getLanguage())
            ->setSerie($serie ?? $book->getSerie());
        if (null !== $source) {
            $book->translates($source);
        }
    }

    // A language the back office does not offer refused
    private function checkLanguage(?string $language): void
    {
        if (null !== $language && !\in_array($language, Book::LANGUAGES, true)) {
            throw new ToolCallException(sprintf('The language is one of %s.', implode(', ', Book::LANGUAGES)));
        }
    }

    // The catalog is the editor's business, as on the back office
    private function guard(): void
    {
        if (!$this->security->isGranted((string) $this->configService->get('site-role-editor'))) {
            throw new ToolCallException('This account may not work on the books.');
        }
    }

    // The token's account, when the application's User entity implements the c975L one
    private function currentUser(): ?UserInterface
    {
        $user = $this->security->getUser();

        return $user instanceof UserInterface ? $user : null;
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

    // A serie by its id, one in the trash answering as unknown
    private function serie(int $id): Serie
    {
        $serie = $this->serieRepository->find($id);
        if (!$serie instanceof Serie || $serie->isDeleted()) {
            throw new ToolCallException(sprintf('No serie %d.', $id));
        }

        return $serie;
    }

    // A book in a list
    /** @return array{id: ?int, title: ?string, number: ?int, serie: ?string, language: ?string, published: ?string} */
    private function summary(Book $book): array
    {
        return [
            'id' => $book->getId(),
            'title' => $book->getTitle(),
            'number' => $book->getNumber(),
            'serie' => $book->getSerie()?->getTitle(),
            'language' => $book->getLanguage(),
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
