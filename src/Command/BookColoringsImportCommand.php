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
use c975L\BookBundle\Entity\BookColoring;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Service\BookColoringPalette;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use c975L\ConfigBundle\Management\ArchivePath;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// Drawings to colour laid on their books from a manifest, the way an editor would one by one: the PNG uploaded, its crayons read off its illustration, its day set. Each drawing comes out with its book's documents to print, never before the manifest's "notBefore". Run again, it uploads nothing twice: a drawing already on its book, known by its fingerprint, only gets its title, day and crayons again. Manifest: {"notBefore": "2027-01-06", "colorings": [{"book": 1 or "slug", "file": "Ecran/x.png", "source": "Images/Page-0.jpg", "title": "item-1", "kind": "small", "publishedOn": "2027-01-13"}]}, paths relative to it, "source", "title", "kind" (the level, as for documents to print) and "publishedOn" optional
#[AsCommand(name: 'c975l:book:colorings:import', description: 'Adds the drawings to colour a JSON manifest lists to their books, each out with its book\'s documents to print. Lists them without writing with --dry-run.')]
class BookColoringsImportCommand extends AbstractBookManifestImportCommand
{
    // The manifest's first day, read before its drawings
    private ?\DateTimeImmutable $notBefore = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        BookRepository $bookRepository,
        private readonly BookColoringPalette $palette,
        BookCustomizationRegistry $customizationRegistry,
    ) {
        parent::__construct($bookRepository, $customizationRegistry);
    }

    // The drawings, listed under this key
    protected function entriesKey(): string
    {
        return 'colorings';
    }

    // What the report counts
    protected function noun(): string
    {
        return 'dessin(s)';
    }

    // The first day no drawing comes out before, a misspelt one stopping the import
    protected function prepare(array $manifest, SymfonyStyle $io): bool
    {
        try {
            $this->notBefore = $this->day($manifest['notBefore'] ?? null);
        } catch (\DateMalformedStringException) {
            $io->error('Date illisible : ' . $manifest['notBefore']);

            return false;
        }

        return true;
    }

    // One drawing on its book, created or brought up to date, flushed on its own so a failure further down keeps what is done. Null for a book or a file not found or a level the site doesn't name, throws on a day misspelt
    /** @param array<mixed> $entry */
    protected function import(array $entry, string $directory, bool $dryRun): ?string
    {
        $book = $this->book($entry['book'] ?? null);
        // Read beside the manifest only, and a PNG only: a path out of its folder or another kind of file is left out like a missing one (see ArchivePath)
        $file = ArchivePath::resolve($directory, $entry['file'] ?? null);
        $kind = $entry['kind'] ?? null;
        if (null === $book || null === $file || 'png' !== strtolower(pathinfo($file, \PATHINFO_EXTENSION)) || !$this->isKnownKind($kind)) {
            return null;
        }

        $fingerprint = hash_file('xxh128', $file) ?: null;
        $coloring = $book->getColorings()->findFirst(static fn (int $key, BookColoring $known): bool => $fingerprint === $known->getFingerprint());
        $publishedOn = $this->publishedOn($book, $entry['publishedOn'] ?? null, $this->notBefore);
        $line = \sprintf('%s %s : %s, %s%s', null === $coloring ? '+' : '=', $book->getSlug(), basename($file), $publishedOn?->format('d/m/Y') ?? 'aussitôt', null === $kind ? '' : ', ' . $kind);
        if ($dryRun) {
            return $line;
        }

        if (null === $coloring) {
            $coloring = new BookColoring()
                ->setPosition($book->getColorings()->count() + 1)
                ->setFile(new ReplacingFile($file, true, false, false));
            $book->addColoring($coloring);
            $this->em->persist($coloring);
        }
        $coloring
            ->setTitle(\is_string($entry['title'] ?? null) ? $entry['title'] : $coloring->getTitle())
            ->setKind($kind ?? $coloring->getKind())
            ->setPublishedOn($publishedOn);
        $sourcePath = ArchivePath::resolve($directory, $entry['source'] ?? null);
        $source = null !== $sourcePath ? $this->palette->fromFile($sourcePath) : [];
        if ([] !== $source) {
            $coloring->setPalette($source);
        }
        $this->em->flush();

        return $line;
    }

    // The day given, else the last of the book's documents to print, and never before the manifest's first day
    private function publishedOn(Book $book, mixed $given, ?\DateTimeImmutable $notBefore): ?\DateTimeImmutable
    {
        $day = $this->day($given);
        foreach (null === $day ? $book->getPrintables() : [] as $printable) {
            if (null !== $printable->getPublishedOn() && (null === $day || $printable->getPublishedOn() > $day)) {
                $day = $printable->getPublishedOn();
            }
        }

        return null === $notBefore || (null !== $day && $day > $notBefore) ? $day ?? $notBefore : $notBefore;
    }
}
