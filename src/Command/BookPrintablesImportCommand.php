<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Command;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookPrintable;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use c975L\ConfigBundle\Management\ArchivePath;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// Documents to print laid on their books from a manifest, the way an editor would one by one: the PDF uploaded, named and given its thumbnail by the listeners an upload runs (see BookPrintableNameListener), its title, level and day set. Run again, it uploads nothing twice: a document already on its book, known by the bytes of its stored file, only gets its title, level and day again, and one of the same level and title whose bytes changed gets the new file in its place. Manifest: {"printables": [{"book": 1 or "slug", "file": "Petits-Coloriages.pdf", "title": "item-1", "kind": "small", "publishedOn": "2027-01-13"}]}, paths relative to it, "title", "kind" and "publishedOn" optional, a key left out leaving a document already there as it is
#[AsCommand(name: 'c975l:book:printables:import', description: 'Adds the documents to print a JSON manifest lists to their books. Lists them without writing with --dry-run.')]
class BookPrintablesImportCommand extends AbstractBookManifestImportCommand
{
    // The documents this run already laid or recognised, by object id: two new PDFs sharing a title and a level in one manifest are two documents, the second never replacing the first
    /** @var array<int, true> */
    private array $laid = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        BookRepository $bookRepository,
        BookCustomizationRegistry $customizationRegistry,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
        parent::__construct($bookRepository, $customizationRegistry);
    }

    // The documents, listed under this key
    protected function entriesKey(): string
    {
        return 'printables';
    }

    // What the report counts
    protected function noun(): string
    {
        return 'document(s)';
    }

    // A run starts with no document laid
    protected function prepare(array $manifest, SymfonyStyle $io): bool
    {
        $this->laid = [];

        return true;
    }

    // One document on its book, created, replaced or brought up to date, flushed on its own so a failure further down keeps what is done. Null for a book or a file not found or a level the site doesn't name, throws on a day misspelt
    /** @param array<mixed> $entry */
    protected function import(array $entry, string $directory, bool $dryRun): ?string
    {
        $book = $this->book($entry['book'] ?? null);
        // Read beside the manifest only, and a PDF by its name and its bytes: it lands under public/ keeping its extension, where another kind of file would be served from the site's own origin (see ArchivePath)
        $file = ArchivePath::resolve($directory, $entry['file'] ?? null);
        $kind = $entry['kind'] ?? null;
        $title = \is_string($entry['title'] ?? null) ? $entry['title'] : null;
        if (null === $book || null === $file || !$this->isPdf($file) || !$this->isKnownKind($kind)) {
            return null;
        }

        // Only a day written counts as given: a null or a number would release a document still ahead
        $publishedOn = $this->day($entry['publishedOn'] ?? null);
        $printable = $this->stored($book, $file);
        $replaced = null === $printable ? $this->sameSlot($book, $kind, $title) : null;
        $sign = match (true) {
            null !== $printable => '=',
            null !== $replaced => '~',
            default => '+',
        };
        $printable ??= $replaced;
        $line = \sprintf('%s %s : %s, %s%s', $sign, $book->getSlug(), basename($file), ($publishedOn ?? $printable?->getPublishedOn())?->format('d/m/Y') ?? 'aussitôt', null === $kind ? '' : ', ' . $kind);
        if ($dryRun) {
            return $line;
        }

        $this->laid[spl_object_id($this->write($book, $printable, '=' === $sign ? null : $file, $kind, $title, $publishedOn))] = true;

        return $line;
    }

    // Created at the end of its book's documents, or given the new file in place of the old one (none to upload for a document already holding its bytes), then whatever the entry names: a key left out keeps what a document already carries, its day possibly still ahead
    private function write(Book $book, ?BookPrintable $printable, ?string $upload, ?string $kind, ?string $title, ?\DateTimeImmutable $publishedOn): BookPrintable
    {
        if (null === $printable) {
            $printable = new BookPrintable()->setPosition($book->getPrintables()->count() + 1);
            $book->addPrintable($printable);
            $this->em->persist($printable);
        }
        if (null !== $upload) {
            $printable->setFile(new ReplacingFile($upload, true, false, false));
        }
        $printable
            ->setTitle($title ?? $printable->getTitle())
            ->setKind($kind ?? $printable->getKind())
            ->setPublishedOn($publishedOn ?? $printable->getPublishedOn());
        $this->em->flush();

        return $printable;
    }

    // The document of the book whose stored file holds the same bytes, its size compared first so only a likely one is read
    private function stored(Book $book, string $file): ?BookPrintable
    {
        $size = filesize($file);
        $fingerprint = null;
        foreach ($book->getPrintables() as $printable) {
            $stored = $this->projectDir . '/public/' . $printable->getName();
            if (null === $printable->getName() || $size !== $printable->getSize() || !is_file($stored)) {
                continue;
            }
            $fingerprint ??= hash_file('xxh128', $file);
            if ($fingerprint === hash_file('xxh128', $stored)) {
                return $printable;
            }
        }

        return null;
    }

    // The document of the book at the same level under the same title, whose file a new version replaces; none without a title to tell it by, nor one this run already laid
    private function sameSlot(Book $book, ?string $kind, ?string $title): ?BookPrintable
    {
        if (null === $title) {
            return null;
        }

        return $book->getPrintables()->findFirst(fn (int $key, BookPrintable $known): bool => !isset($this->laid[spl_object_id($known)]) && $title === $known->getTitle() && $kind === $known->getKind());
    }

    // Named a PDF and starting with its signature
    private function isPdf(string $file): bool
    {
        return 'pdf' === strtolower(pathinfo($file, \PATHINFO_EXTENSION)) && '%PDF-' === file_get_contents($file, false, null, 0, 5);
    }
}
