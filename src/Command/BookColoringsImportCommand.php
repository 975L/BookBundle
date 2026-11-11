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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// Drawings to colour laid on their books from a manifest, the way an editor would one by one: the PNG uploaded, its crayons read off its illustration, its day set. Each drawing comes out with its book's documents to print, never before the manifest's "notBefore". Run again, it uploads nothing twice: a drawing already on its book, known by its fingerprint, only gets its title, day and crayons again. Manifest: {"notBefore": "2027-01-06", "colorings": [{"book": 1 or "slug", "file": "Ecran/x.png", "source": "Images/Page-0.jpg", "title": "item-1", "kind": "small", "publishedOn": "2027-01-13"}]}, paths relative to it, "source", "title", "kind" (the level, as for documents to print) and "publishedOn" optional
#[AsCommand(name: 'c975l:book:colorings:import', description: 'Adds the drawings to colour a JSON manifest lists to their books, each out with its book\'s documents to print. Lists them without writing with --dry-run.')]
class BookColoringsImportCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BookRepository $bookRepository,
        private readonly BookColoringPalette $palette,
        private readonly BookCustomizationRegistry $customizationRegistry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('manifest', InputArgument::REQUIRED, 'The JSON manifest listing the drawings')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Lists what would be written, writing nothing')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = (string) $input->getArgument('manifest');
        $manifest = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (!\is_array($manifest) || !\is_array($manifest['colorings'] ?? null)) {
            $io->error('Manifeste illisible : ' . $path);

            return Command::FAILURE;
        }

        try {
            $notBefore = \is_string($manifest['notBefore'] ?? null) ? new \DateTimeImmutable($manifest['notBefore']) : null;
        } catch (\DateMalformedStringException) {
            $io->error('Date illisible : ' . $manifest['notBefore']);

            return Command::FAILURE;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $written = [];
        $failed = [];
        foreach ($manifest['colorings'] as $entry) {
            // A day the manifest misspells leaves its drawing out, the others written
            try {
                $line = \is_array($entry) ? $this->import($entry, \dirname($path), $notBefore, $dryRun) : null;
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
        $io->success(\sprintf($dryRun ? '%d dessin(s) à écrire, relancer sans --dry-run pour le faire.' : '%d dessin(s) écrit(s).', \count($written)));

        return [] === $failed ? Command::SUCCESS : Command::FAILURE;
    }

    // One drawing on its book, created or brought up to date, flushed on its own so a failure further down keeps what is done. Null for a book or a file not found or a level the site doesn't name, throws on a day misspelt
    /** @param array<mixed> $entry */
    private function import(array $entry, string $directory, ?\DateTimeImmutable $notBefore, bool $dryRun): ?string
    {
        $book = $this->book($entry['book'] ?? null);
        $file = \is_string($entry['file'] ?? null) ? $directory . '/' . $entry['file'] : '';
        $kind = $entry['kind'] ?? null;
        if (null === $book || !is_file($file) || !$this->isKnownKind($kind)) {
            return null;
        }

        $fingerprint = hash_file('xxh128', $file) ?: null;
        $coloring = $book->getColorings()->findFirst(static fn (int $key, BookColoring $known): bool => $fingerprint === $known->getFingerprint());
        $publishedOn = $this->publishedOn($book, $entry['publishedOn'] ?? null, $notBefore);
        $line = \sprintf('%s %s : %s, %s%s', null === $coloring ? '+' : '=', $book->getSlug(), basename($file), $publishedOn?->format('d/m/Y') ?? 'aussitôt', null === $kind ? '' : ', ' . $kind);
        if ($dryRun) {
            return $line;
        }

        if (null === $coloring) {
            $coloring = new BookColoring()
                ->setPosition($book->getColorings()->count() + 1)
                ->setFile(new ReplacingFile($file));
            $book->addColoring($coloring);
            $this->em->persist($coloring);
        }
        $coloring
            ->setTitle(\is_string($entry['title'] ?? null) ? $entry['title'] : $coloring->getTitle())
            ->setKind($kind ?? $coloring->getKind())
            ->setPublishedOn($publishedOn);
        $source = \is_string($entry['source'] ?? null) ? $this->palette->fromFile($directory . '/' . $entry['source']) : [];
        if ([] !== $source) {
            $coloring->setPalette($source);
        }
        $this->em->flush();

        return $line;
    }

    // No level, or one of the site's documents to print: a misspelt one would group its drawing apart, and outrun the column
    /** @phpstan-assert-if-true ?string $kind */
    private function isKnownKind(mixed $kind): bool
    {
        return null === $kind || (\is_string($kind) && \array_key_exists($kind, $this->customizationRegistry->getPrintableKinds()));
    }

    // A number is the one the book's short link carries (see BookRepository::findOneByNumber()), anything else its slug
    private function book(mixed $book): ?Book
    {
        return match (true) {
            \is_int($book) => $this->bookRepository->findOneByNumber($book),
            \is_string($book) && '' !== $book => $this->bookRepository->findOneBy(['slug' => $book]),
            default => null,
        };
    }

    // The day given, else the last of the book's documents to print, and never before the manifest's first day
    private function publishedOn(Book $book, mixed $given, ?\DateTimeImmutable $notBefore): ?\DateTimeImmutable
    {
        $day = \is_string($given) ? new \DateTimeImmutable($given) : null;
        foreach (null === $day ? $book->getPrintables() : [] as $printable) {
            if (null !== $printable->getPublishedOn() && (null === $day || $printable->getPublishedOn() > $day)) {
                $day = $printable->getPublishedOn();
            }
        }

        return null === $notBefore || (null !== $day && $day > $notBefore) ? $day ?? $notBefore : $notBefore;
    }
}
