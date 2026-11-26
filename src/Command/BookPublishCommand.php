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
use c975L\BookBundle\Entity\BookCategory;
use c975L\BookBundle\Entity\BookContributor;
use c975L\BookBundle\Entity\BookEdition;
use c975L\BookBundle\Entity\BookEditionFile;
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Media;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Enum\BookChannel;
use c975L\BookBundle\Enum\BookContributorRole;
use c975L\BookBundle\Enum\BookEditionFileKind;
use c975L\BookBundle\Enum\BookEditionKind;
use c975L\BookBundle\Enum\BookMediaKind;
use c975L\BookBundle\Repository\BookCategoryRepository;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Repository\ContributorRepository;
use c975L\BookBundle\Repository\SerieRepository;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\BookShopPublisher;
use c975L\BookBundle\Service\BookVersionPublisher;
use c975L\ConfigBundle\Management\ArchivePath;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// One book written from a manifest, the way the back-office would: found by its slug or created, its texts, day, serie, translation, categories and contributors set, its medias and its editions with their files uploaded, visible from its day on, then written into the shop as saving it there does. Run again, it brings the same book up to date, a media or a file replaced only when the manifest names one of its kind. Manifest: {"slug": "item-1", "serie": "catalog", "title": "item-1", "published": "2026-12-03", "language": "en", "summary": "<p>…</p>", "age": "4-8", "number": 1, "translates": "item-0", "categories": ["catalog"], "contributors": [{"contributor": "item-2", "role": "narrator"}], "medias": {"cover": "cover.jpg", "page": ["page2.jpg"]}, "editions": [{"kind": "digital", "isbn": "…", "pages": 16, "format": "…", "price": 0, "currency": "EUR", "channels": ["shop", "onix"], "files": {"pdf": "book.pdf", "epub": {"file": "book.epub", "readAloud": true}}}]}, file paths relative to it, all but "slug", "serie", "title" and "published" optional; the last line printed is "BOOK <id> <address>". With "newVersion": true, a book already out is replaced by a new version on its day: what came out is set aside as BookVersionPublisher does, the manifest kept until then under var/book/versions and published by --pending, which the nightly maintenance runs
#[AsCommand(name: 'c975l:book:publish', description: 'Creates or updates the book a JSON manifest describes, with its medias and editions, visible from its day on, writes it into the shop and prints its address. A new version of a book already out waits for its day, published by --pending. Shows it without writing with --dry-run.')]
class BookPublishCommand extends Command
{
    // Where a new version waiting for its day is kept, one folder per book: its manifest and its files as they were sent
    public const string VERSIONS_DIRECTORY = 'var/book/versions';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BookRepository $bookRepository,
        private readonly SerieRepository $serieRepository,
        private readonly BookCategoryRepository $categoryRepository,
        private readonly ContributorRepository $contributorRepository,
        private readonly BookPublicUrlResolver $urlResolver,
        private readonly BookShopPublisher $shopPublisher,
        private readonly BookVersionPublisher $versionPublisher,
        private readonly TranslatorInterface $translator,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('manifest', InputArgument::OPTIONAL, 'The JSON manifest describing the book')
            ->addOption('pending', null, InputOption::VALUE_NONE, 'Publishes the new versions whose day has come')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Shows what would be written, writing nothing')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        if ($input->getOption('pending')) {
            return $this->pending($io, $output, $dryRun);
        }
        $path = (string) $input->getArgument('manifest');
        if ('' === $path) {
            $io->error('Manifeste attendu, ou --pending.');

            return Command::FAILURE;
        }

        return $this->publish($path, $io, $output, $dryRun, false);
    }

    // The new versions kept until their day, published once it has come and their folder then removed; one refused stays for the next night
    private function pending(SymfonyStyle $io, OutputInterface $output, bool $dryRun): int
    {
        $code = Command::SUCCESS;
        foreach (glob($this->projectDir . '/' . self::VERSIONS_DIRECTORY . '/*/manifest.json') ?: [] as $path) {
            $day = $this->day(json_decode((string) file_get_contents($path), true)['published'] ?? null);
            if (null === $day || $day > new \DateTimeImmutable('today')) {
                continue;
            }
            $done = $this->publish($path, $io, $output, $dryRun, true);
            if (Command::SUCCESS === $done && !$dryRun) {
                new Filesystem()->remove(\dirname($path));
            }
            $code = Command::SUCCESS === $done ? $code : Command::FAILURE;
        }

        return $code;
    }

    // One manifest published, a new version not due yet kept for its day instead; $due when its day has come
    private function publish(string $path, SymfonyStyle $io, OutputInterface $output, bool $dryRun, bool $due): int
    {
        $read = $this->read($path);
        if (null === $read) {
            $io->error('Manifeste illisible, série inconnue, ou slug, titre ou date manquant : ' . $path);

            return Command::FAILURE;
        }
        ['manifest' => $manifest, 'serie' => $serie, 'published' => $published] = $read;

        // Everything named checked before anything is written: a missing file, an unknown kind, a value its column cannot hold or a book to translate that is not there refuse the whole manifest, a category or a contributor the site does not hold is only left out
        $errors = [...$this->files(\dirname($path), $manifest)['missing'], ...$this->unknownKinds($manifest), ...$this->unknownLanguage($manifest)];
        $source = $this->source($manifest, $errors);
        if ([] !== $errors) {
            $io->error(['Manifeste refusé :', ...$errors]);

            return Command::FAILURE;
        }
        $book = $this->bookRepository->findOneBy(['slug' => $manifest['slug']]);
        if (true === $book?->isDeleted()) {
            $io->error(\sprintf('Livre %s à la corbeille, le restaurer d\'abord.', $manifest['slug']));

            return Command::FAILURE;
        }
        $version = $this->isNewVersion($manifest, $book);
        $io->text(\sprintf('%s %s « %s », %s, %d édition(s)%s', null === $book ? '+' : '=', $manifest['slug'], trim($manifest['title']), $published->format('d/m/Y'), \count($this->list($manifest['editions'] ?? null)), $version ? ', nouvelle version' : ''));
        if ($dryRun) {
            return Command::SUCCESS;
        }

        // A new version not due yet waits for its day, the book as it came out staying online until then
        if ($version && !$due && $published > new \DateTimeImmutable('today')) {
            $this->keep($path, $manifest['slug'], $this->files(\dirname($path), $manifest)['found']);
            $io->text(\sprintf('Nouvelle version gardée jusqu\'au %s, publiée cette nuit-là par --pending', $published->format('d/m/Y')));
            $output->writeln(\sprintf('BOOK %d %s', $book?->getId(), $this->urlResolver->resolve('book_display', ['slug' => $book?->getSlug()]) ?? ''));

            return Command::SUCCESS;
        }

        // One transaction for what came out set aside and the book written: a failure in between would otherwise set it aside a second time the next night
        $book ??= $this->created($manifest['slug'], $serie);
        $left = $this->em->wrapInTransaction(fn (): array => $this->write($book, $manifest, ['serie' => $serie, 'published' => $published, 'source' => $source], \dirname($path), $version));
        if ([] !== $left) {
            $io->warning(['Absent du site, laissé de côté :', ...$left]);
        }
        $this->shop($book, $io);

        $output->writeln(\sprintf('BOOK %d %s', $book->getId(), $this->urlResolver->resolve('book_display', ['slug' => $book->getSlug()]) ?? ''));

        return Command::SUCCESS;
    }

    // What came out set aside when a new version replaces it, then the book filled, its medias laid and its editions written; the categories and contributors the site does not hold returned
    /**
     * @param array<mixed>                                                      $manifest
     * @param array{serie: Serie, published: \DateTimeImmutable, source: ?Book} $placed
     *
     * @return list<string>
     */
    private function write(Book $book, array $manifest, array $placed, string $directory, bool $version): array
    {
        if ($version) {
            $this->versionPublisher->setAside($book, $this->translator->trans('label.previous_version_suffix', [], 'book'));
        }
        $this->fill($book, $manifest, $placed['serie'], $placed['published'], $placed['source']);
        $left = [...$this->categories($book, $manifest), ...$this->contributors($book, $manifest)];
        $this->lay($book, $this->files($directory, $manifest)['medias']);
        $this->editions($book, $manifest, $directory);
        $this->em->flush();

        return $left;
    }

    // Whether the manifest asks for a new version of a book that has come out and is not replaced yet, what came out being set aside on its day
    /** @param array<mixed> $manifest */
    private function isNewVersion(array $manifest, ?Book $book): bool
    {
        $out = $book?->getPublished();

        return true === ($manifest['newVersion'] ?? false) && null !== $out && $out <= new \DateTimeImmutable('today') && null === $book->getNewerVersion();
    }

    // The manifest and the files it names kept for the night of its day, under the paths it gives them, nothing else of its folder copied; a version kept before for the same book replaced
    /** @param array<string, string> $found */
    private function keep(string $path, string $slug, array $found): void
    {
        $target = $this->projectDir . '/' . self::VERSIONS_DIRECTORY . '/' . $slug;
        $filesystem = new Filesystem();
        $filesystem->remove($target);
        $filesystem->copy($path, $target . '/manifest.json');
        foreach ($found as $relative => $file) {
            $filesystem->copy($file, $target . '/' . $relative);
        }
    }

    // The manifest with its serie and its day, null when it is unreadable or lacks its slug (lower case words joined by hyphens, as an address takes it), serie, title or day
    /** @return array{manifest: array<mixed>, serie: Serie, published: \DateTimeImmutable}|null */
    private function read(string $path): ?array
    {
        $manifest = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (!\is_array($manifest) || !\is_string($manifest['slug'] ?? null) || 1 !== preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $manifest['slug']) || mb_strlen($manifest['slug']) > 100 || !$this->filled($manifest['title'] ?? null)) {
            return null;
        }
        $serie = \is_string($manifest['serie'] ?? null) ? $this->serieRepository->findOneBy(['slug' => $manifest['serie'], 'isDeleted' => false]) : null;
        $published = $this->day($manifest['published'] ?? null);

        return null === $serie || null === $published ? null : ['manifest' => $manifest, 'serie' => $serie, 'published' => $published];
    }

    // A string holding more than blanks
    private function filled(mixed $value): bool
    {
        return \is_string($value) && '' !== trim($value);
    }

    // A day written YYYY-MM-DD, null for anything else
    private function day(mixed $day): ?\DateTimeImmutable
    {
        return \is_string($day) && 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) ? (\DateTimeImmutable::createFromFormat('!Y-m-d', $day) ?: null) : null;
    }

    // A manifest's list, nothing for anything else
    /** @return list<mixed> */
    private function list(mixed $value): array
    {
        return \is_array($value) ? array_values($value) : [];
    }

    // The book it translates, by its slug, its absence or the book itself added to the errors
    /**
     * @param array<mixed> $manifest
     * @param list<string> $errors
     */
    private function source(array $manifest, array &$errors): ?Book
    {
        if (!$this->filled($manifest['translates'] ?? null)) {
            return null;
        }
        $source = $manifest['translates'] === $manifest['slug'] ? null : $this->bookRepository->findOneBy(['slug' => $manifest['translates'], 'isDeleted' => false]);
        if (null === $source) {
            $errors[] = 'livre traduit ' . json_encode($manifest['translates'], \JSON_UNESCAPED_UNICODE);
        }

        return $source;
    }

    // The media, edition, file, channel and role kinds the manifest names and the bundle does not know
    /**
     * @param array<mixed> $manifest
     *
     * @return list<string>
     */
    private function unknownKinds(array $manifest): array
    {
        $medias = array_keys(array_diff_key(\is_array($manifest['medias'] ?? null) ? $manifest['medias'] : [], array_flip(array_column(BookMediaKind::cases(), 'value'))));
        $unknown = array_map(static fn (int | string $kind): string => 'média ' . $kind, $medias);
        foreach ($this->list($manifest['editions'] ?? null) as $edition) {
            $unknown = [...$unknown, ...$this->unknownInEdition(\is_array($edition) ? $edition : [])];
        }
        foreach ($this->list($manifest['contributors'] ?? null) as $contributor) {
            $role = \is_array($contributor) ? ($contributor['role'] ?? null) : null;
            if (null !== $role && null === BookContributorRole::tryFrom((string) $role)) {
                $unknown[] = 'rôle ' . $role;
            }
        }

        return $unknown;
    }

    // The kind of an edition, of its files and of its channels, those the bundle does not know or the edition does not hold, and the values its columns cannot hold
    /**
     * @param array<mixed> $edition
     *
     * @return list<string>
     */
    private function unknownInEdition(array $edition): array
    {
        $kind = $edition['kind'] ?? null;
        if (!\is_string($kind) || null === BookEditionKind::tryFrom($kind)) {
            return ['édition ' . json_encode($kind, \JSON_UNESCAPED_UNICODE)];
        }
        $held = BookEditionFileKind::forEdition($kind);
        $files = array_filter(array_keys(\is_array($edition['files'] ?? null) ? $edition['files'] : []), static fn (int | string $file): bool => !\in_array(BookEditionFileKind::tryFrom((string) $file), $held, true));
        $channels = array_filter($this->list($edition['channels'] ?? null), static fn (mixed $channel): bool => null === BookChannel::tryFrom((string) $channel));

        return [...array_map(static fn (int | string $file): string => 'fichier ' . $file, array_values($files)), ...array_map(static fn (mixed $channel): string => 'canal ' . $channel, array_values($channels)), ...$this->overflowing($edition)];
    }

    // The values of an edition its columns cannot hold: a text longer than its column, a price below zero
    /**
     * @param array<mixed> $edition
     *
     * @return list<string>
     */
    private function overflowing(array $edition): array
    {
        $refused = [];
        foreach (['isbn' => 13, 'format' => 50, 'currency' => 3] as $key => $max) {
            if (\is_string($edition[$key] ?? null) && mb_strlen($edition[$key]) > $max) {
                $refused[] = $key . ' ' . json_encode($edition[$key], \JSON_UNESCAPED_UNICODE);
            }
        }
        foreach ([$edition, ...array_filter(\is_array($edition['files'] ?? null) ? $edition['files'] : [], is_array(...))] as $priced) {
            if (\is_int($priced['price'] ?? null) && $priced['price'] < 0) {
                $refused[] = 'prix ' . $priced['price'];
            }
        }

        return $refused;
    }

    // The language the manifest names when the back office does not offer it, and an age longer than its column
    /**
     * @param array<mixed> $manifest
     *
     * @return list<string>
     */
    private function unknownLanguage(array $manifest): array
    {
        $language = $manifest['language'] ?? null;
        $refused = null === $language || \in_array($language, Book::LANGUAGES, true) ? [] : ['langue ' . json_encode($language, \JSON_UNESCAPED_UNICODE)];

        return \is_string($manifest['age'] ?? null) && mb_strlen($manifest['age']) > 20 ? [...$refused, 'âge ' . json_encode($manifest['age'], \JSON_UNESCAPED_UNICODE)] : $refused;
    }

    // Every file the manifest names, read beside it only (see ArchivePath) and in an extension its kind takes: the book's medias with their kind, every file found by the path the manifest gives it, those not found listed
    /**
     * @param array<mixed> $manifest
     *
     * @return array{medias: list<array{kind: string, file: string}>, found: array<string, string>, missing: list<string>}
     */
    private function files(string $directory, array $manifest): array
    {
        $medias = [];
        $found = [];
        $missing = [];
        foreach (\is_array($manifest['medias'] ?? null) ? $manifest['medias'] : [] as $kind => $named) {
            foreach (\is_array($named) ? $named : [$named] as $relative) {
                $file = $this->resolve($directory, $relative, Media::FILE_UPLOAD_EXTENSIONS, $missing);
                if (null !== $file) {
                    $medias[] = ['kind' => (string) $kind, 'file' => $file];
                    $found[(string) $relative] = $file;
                }
            }
        }
        foreach ($this->list($manifest['editions'] ?? null) as $edition) {
            foreach (\is_array($edition) && \is_array($edition['files'] ?? null) ? $edition['files'] : [] as $kind => $named) {
                $relative = \is_array($named) ? ($named['file'] ?? null) : $named;
                $file = $this->resolve($directory, $relative, BookEditionFileKind::tryFrom((string) $kind)?->extensions() ?? [], $missing);
                if (null !== $file) {
                    $found[(string) $relative] = $file;
                }
            }
        }

        return ['medias' => $medias, 'found' => $found, 'missing' => $missing];
    }

    // A file beside the manifest in one of the extensions given, its absence or another extension added to the missing ones
    /**
     * @param list<string> $extensions
     * @param list<string> $missing
     */
    private function resolve(string $directory, mixed $relative, array $extensions, array &$missing): ?string
    {
        $file = \is_string($relative) ? ArchivePath::resolve($directory, $relative) : null;
        if (null === $file || !\in_array(strtolower(pathinfo($file, \PATHINFO_EXTENSION)), $extensions, true)) {
            $missing[] = 'fichier ' . json_encode($relative, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

            return null;
        }

        return $file;
    }

    // A new book under the slug the manifest gives, its author and illustrator those of its serie
    private function created(string $slug, Serie $serie): Book
    {
        $book = new Book()
            ->setSlug($slug)
            ->setCreation(new \DateTimeImmutable())
            ->setAuthor($serie->getAuthor())
            ->setIllustrator($serie->getIllustrator());
        $this->em->persist($book);

        return $book;
    }

    // The texts, day, serie and translation the manifest gives, the book shown from its day on; a text left out of the manifest keeps the one it had
    /** @param array<mixed> $manifest */
    private function fill(Book $book, array $manifest, Serie $serie, \DateTimeImmutable $published, ?Book $source): void
    {
        $book
            ->setTitle(mb_substr(trim($manifest['title']), 0, 100))
            ->setSerie($serie)
            ->setPublished($published)
            ->setHidden(false)
            ->setModification(new \DateTimeImmutable());
        foreach (['summary' => $book->setSummary(...), 'language' => $book->setLanguage(...), 'age' => $book->setAge(...)] as $key => $setter) {
            if (\is_string($manifest[$key] ?? null)) {
                $setter($manifest[$key]);
            }
        }
        if (\is_int($manifest['number'] ?? null)) {
            $book->setNumber($manifest['number']);
        }
        if (null !== $source) {
            $book->translates($source);
        }
    }

    // The categories the manifest names in place of those the book had, by their slug, those the site does not hold returned
    /**
     * @param array<mixed> $manifest
     *
     * @return list<string>
     */
    private function categories(Book $book, array $manifest): array
    {
        if (!\array_key_exists('categories', $manifest)) {
            return [];
        }
        foreach ($book->getCategories()->toArray() as $category) {
            $book->removeCategory($category);
        }
        $missing = [];
        foreach ($this->list($manifest['categories']) as $slug) {
            $category = $this->filled($slug) ? $this->categoryRepository->findOneBy(['slug' => $slug]) : null;
            if (!$category instanceof BookCategory) {
                $missing[] = 'catégorie ' . json_encode($slug, \JSON_UNESCAPED_UNICODE);
                continue;
            }
            $book->addCategory($category);
        }

        return $missing;
    }

    // The contributors the manifest names in place of those the book had, in its order, a credit already held kept so that a run again never inserts it twice, those the site does not hold returned
    /**
     * @param array<mixed> $manifest
     *
     * @return list<string>
     */
    private function contributors(Book $book, array $manifest): array
    {
        if (!\array_key_exists('contributors', $manifest)) {
            return [];
        }
        $held = $book->getContributors()->toArray();
        $kept = [];
        $missing = [];
        foreach ($this->list($manifest['contributors']) as $position => $named) {
            $person = \is_array($named) && $this->filled($named['contributor'] ?? null) ? $this->contributorRepository->findOneBy(['slug' => $named['contributor']]) : null;
            if (!$person instanceof Contributor) {
                $missing[] = 'contributeur ' . json_encode(\is_array($named) ? ($named['contributor'] ?? null) : $named, \JSON_UNESCAPED_UNICODE);
                continue;
            }
            $kept[] = $this->credit($book, $person, (string) ($named['role'] ?? BookContributorRole::Narrator->value))->setPosition($position);
        }
        foreach ($held as $credit) {
            if (!\in_array($credit, $kept, true)) {
                $book->removeContributor($credit);
            }
        }

        return $missing;
    }

    // The credit of a person in a role the book already holds, or was given earlier in the same manifest, else a new one added to it
    private function credit(Book $book, Contributor $person, string $role): BookContributor
    {
        foreach ($book->getContributors() as $credit) {
            if ($credit->getContributor() === $person && $credit->getRole() === $role) {
                return $credit;
            }
        }
        $credit = new BookContributor()->setContributor($person)->setRole($role);
        $book->addContributor($credit);
        $this->em->persist($credit);

        return $credit;
    }

    // The medias the manifest names, in place of those of their kind, the others kept
    /** @param list<array{kind: string, file: string}> $medias */
    private function lay(Book $book, array $medias): void
    {
        $kinds = array_unique(array_column($medias, 'kind'));
        foreach ($book->getMedias()->toArray() as $media) {
            if (\in_array((string) $media->getKind(), $kinds, true)) {
                $book->removeMedia($media);
            }
        }
        foreach ($medias as $position => $named) {
            $media = new BookMedia()
                ->setKind($named['kind'])
                ->setPosition($position * 5)
                ->setFile(new ReplacingFile($named['file'], true, false, false));
            $book->addMedia($media);
            $this->em->persist($media);
        }
    }

    // Each edition the manifest names, found by its kind or added, its identifiers, price and channels set and the files it names put in place of those of their kind
    /** @param array<mixed> $manifest */
    private function editions(Book $book, array $manifest, string $directory): void
    {
        foreach ($this->list($manifest['editions'] ?? null) as $position => $named) {
            $edition = $book->getEdition($named['kind']) ?? new BookEdition()->setKind($named['kind'])->setPosition($position);
            $book->addEdition($edition);
            $this->em->persist($edition);
            $edition
                ->setIsbn(\is_string($named['isbn'] ?? null) ? $named['isbn'] : $edition->getIsbn())
                ->setPages(\is_int($named['pages'] ?? null) ? $named['pages'] : $edition->getPages())
                ->setFormat(\is_string($named['format'] ?? null) ? $named['format'] : $edition->getFormat())
                ->setPrice(\is_int($named['price'] ?? null) ? $named['price'] : $edition->getPrice())
                ->setCurrency(\is_string($named['currency'] ?? null) ? $named['currency'] : $edition->getCurrency())
                ->setChannels(\is_array($named['channels'] ?? null) ? array_map(strval(...), $named['channels']) : $edition->getChannels());
            $this->editionFiles($edition, \is_array($named['files'] ?? null) ? $named['files'] : [], $directory);
        }
    }

    // The files of an edition, each in place of its kind's, sold at the edition's price unless it names its own, a cover never sold
    /** @param array<mixed> $files */
    private function editionFiles(BookEdition $edition, array $files, string $directory): void
    {
        foreach ($files as $kind => $named) {
            $kind = BookEditionFileKind::from((string) $kind);
            $relative = \is_array($named) ? $named['file'] : $named;
            $cover = \in_array($kind, [BookEditionFileKind::CoverFront, BookEditionFileKind::CoverBack], true);
            $file = new BookEditionFile()
                ->setPrice($cover ? null : (\is_array($named) && \is_int($named['price'] ?? null) ? $named['price'] : $edition->getPrice()))
                ->setReadAloud(\is_array($named) && true === ($named['readAloud'] ?? false))
                ->setFile(new ReplacingFile((string) ArchivePath::resolve($directory, $relative), true, false, false));
            $edition->setFileOf($kind, $file);
            $this->em->persist($file);
        }
    }

    // The book written into the shop once its files are on disk, as saving it in the back-office does (see BookShopPublishSubscriber), a shop that fails only said, the book being saved already
    private function shop(Book $book, SymfonyStyle $io): void
    {
        if (!$this->shopPublisher->isAvailable()) {
            return;
        }
        try {
            $this->shopPublisher->publish($book);
        } catch (\Throwable $exception) {
            $io->warning('Boutique non mise à jour : ' . $exception->getMessage());
        }
    }
}
