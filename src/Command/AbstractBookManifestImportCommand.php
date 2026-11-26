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
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// What the imports laying files on their books from a JSON manifest share: the manifest read, its entries written one by one and listed, those left out named, the book found by its number or slug and the level checked against the site's (see BookColoringsImportCommand, BookPrintablesImportCommand)
abstract class AbstractBookManifestImportCommand extends Command
{
    public function __construct(
        protected readonly BookRepository $bookRepository,
        protected readonly BookCustomizationRegistry $customizationRegistry,
    ) {
        parent::__construct();
    }

    // The manifest's key listing the entries ("colorings", "printables")
    abstract protected function entriesKey(): string;

    // The word the report counts the entries by ("dessin(s)", "document(s)")
    abstract protected function noun(): string;

    // One entry on its book, its line for the report, or null when left out; throws on a day misspelt
    /** @param array<mixed> $entry */
    abstract protected function import(array $entry, string $directory, bool $dryRun): ?string;

    // What the manifest carries beside its entries, read before them: false stops the import, its error already said
    /** @param array<mixed> $manifest */
    protected function prepare(array $manifest, SymfonyStyle $io): bool
    {
        return true;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('manifest', InputArgument::REQUIRED, 'The JSON manifest listing the files')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Lists what would be written, writing nothing')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = (string) $input->getArgument('manifest');
        $manifest = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (!\is_array($manifest) || !\is_array($manifest[$this->entriesKey()] ?? null)) {
            $io->error('Manifeste illisible : ' . $path);

            return Command::FAILURE;
        }
        if (!$this->prepare($manifest, $io)) {
            return Command::FAILURE;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $written = [];
        $failed = [];
        foreach ($manifest[$this->entriesKey()] as $entry) {
            // A day the manifest misspells leaves its entry out, the others written
            try {
                $line = \is_array($entry) ? $this->import($entry, \dirname($path), $dryRun) : null;
            } catch (\DateMalformedStringException) {
                $line = null;
            }
            if (null === $line) {
                $failed[] = json_encode($entry, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

                continue;
            }
            $written[] = $line;
        }

        if ([] !== $written) {
            $io->listing($written);
        }
        if ([] !== $failed) {
            $io->warning(['Livre ou fichier introuvable, date illisible ou niveau inconnu :', ...$failed]);
        }
        $io->success(\sprintf($dryRun ? '%d %s à écrire, relancer sans --dry-run pour le faire.' : '%d %s écrit(s).', \count($written), $this->noun()));

        return [] === $failed ? Command::SUCCESS : Command::FAILURE;
    }

    // A number is the one the book's short link carries (see BookRepository::findOneByNumber()), anything else its slug
    protected function book(mixed $book): ?Book
    {
        return match (true) {
            \is_int($book) => $this->bookRepository->findOneByNumber($book),
            \is_string($book) && '' !== $book => $this->bookRepository->findOneBy(['slug' => $book]),
            default => null,
        };
    }

    // No level, or one of the site's documents to print: a misspelt one would group its file apart, and outrun the column
    /** @phpstan-assert-if-true ?string $kind */
    protected function isKnownKind(mixed $kind): bool
    {
        return null === $kind || (\is_string($kind) && \array_key_exists($kind, $this->customizationRegistry->getPrintableKinds()));
    }

    // The day an entry gives, null when it gives none, throws when it is misspelt
    protected function day(mixed $given): ?\DateTimeImmutable
    {
        return \is_string($given) ? new \DateTimeImmutable($given) : null;
    }
}
