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
use c975L\BookBundle\Entity\BookEdition;
use c975L\BookBundle\Entity\BookEditionFile;
use c975L\BookBundle\Enum\BookChannel;
use c975L\BookBundle\Enum\BookEditionFileKind;
use c975L\BookBundle\Enum\BookEditionKind;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Service\BookShopPublisher;
use c975L\UiBundle\Contract\ProductCatalogWriterInterface;
use c975L\UiBundle\Model\CatalogProductItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// The one-shot step of the release where the catalog became the home of the files sold: every file the shop holds is copied into the edition it belongs to, with its price, and the shop's rows are stamped with the keys the catalog writes them under from then on - without that, the first save would create every item anew. A product is matched to a book by its slug, then by its title; an item to a kind of file by its slug and its extension, "version originale" sending it to the book's earlier version. What cannot be matched is listed and left alone
#[AsCommand(
    name: 'c975l:book:shop:import',
    description: 'Copies the files the shop sells into the editions of the catalog, once'
)]
class BookShopImportCommand extends Command
{
    public function __construct(
        private readonly BookRepository $bookRepository,
        private readonly EntityManagerInterface $em,
        private readonly ?ProductCatalogWriterInterface $writer = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Says what would be copied without copying anything');
        $this->addOption('product', null, InputOption::VALUE_REQUIRED, 'Only the product of this slug, to try the import on one first');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (null === $this->writer) {
            $io->error('No shop is installed.');

            return Command::FAILURE;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        [$bySlug, $byTitle] = $this->index();
        $copied = [];
        $skipped = [];
        $productKeys = [];
        // The slots filled by this very run, which a dry run never writes and would otherwise see empty again
        $taken = [];
        // The original versions last: a book sold illustrated keeps its illustrated files, its "version originale" then finding the slot taken
        $items = $this->writer->itemsWithFile();
        usort($items, static fn (CatalogProductItem $a, CatalogProductItem $b): int => self::isEarlierVersion($a) <=> self::isEarlierVersion($b));
        foreach ($items as $item) {
            if (null !== $item->key || (null !== $input->getOption('product') && $input->getOption('product') !== $item->productSlug)) {
                continue;
            }

            $place = $this->place($item, $bySlug, $byTitle, $productKeys, $taken);
            if (\is_string($place)) {
                $skipped[] = [$item->productSlug, $item->slug, $place];
                continue;
            }

            [$edition, $kind, $productKey] = $place;
            $productKeys[$productKey] = $item->productSlug;
            $taken[self::slot($edition, $kind)] = true;
            $copied[] = [$item->productSlug, $item->slug, $edition->getBook()?->getSlug() . ' / ' . $edition->getKind() . ' / ' . $kind->value];
            if (!$dryRun) {
                $this->copy($item, $edition, $kind);
                $this->em->flush();
                $this->writer->setKeys((int) $item->id, $productKey, BookShopPublisher::itemKey($edition, $kind->value));
            }
        }

        $io->table(['Product', 'Item', 'Copied into'], $copied);
        if ([] !== $skipped) {
            $io->warning(sprintf('%d file(s) left in the shop alone:', \count($skipped)));
            $io->table(['Product', 'Item', 'Why'], $skipped);
        }
        $io->success(sprintf('%d file(s) %s.', \count($copied), $dryRun ? 'would be copied' : 'copied'));

        return Command::SUCCESS;
    }

    // Where a shop's file goes - the edition, the kind of file and the product's key - or why it goes nowhere
    /**
     * @param array<string, Book>   $bySlug
     * @param array<string, Book>   $byTitle
     * @param array<string, string> $productKeys product key => the shop product already matched to it
     * @param array<string, true>   $taken       the slots this run already filled (see slot())
     *
     * @return array{BookEdition, BookEditionFileKind, string}|string
     */
    private function place(CatalogProductItem $item, array $bySlug, array $byTitle, array $productKeys, array $taken): array | string
    {
        $book = self::book($item, $bySlug, $byTitle);
        $kind = self::kind($item);
        if (null === $book || null === $kind) {
            return null === $book ? 'no book' : 'unknown kind of file';
        }

        $productKey = BookShopPublisher::productKey($book->getLatestVersion());
        if (isset($productKeys[$productKey]) && $productKeys[$productKey] !== $item->productSlug) {
            return 'another product already matched "' . $book->getSlug() . '"';
        }

        $edition = $this->edition($book, $kind);

        return null === $edition->getFileOf($kind) && !isset($taken[self::slot($edition, $kind)]) ? [$edition, $kind, $productKey] : 'already a ' . $kind->value . ' on "' . $book->getSlug() . '"';
    }

    // The book an item belongs to, by its product's slug then its title. A "version originale" belongs to the earlier version of the book, or to the book itself when it never had another one
    /**
     * @param array<string, Book> $bySlug
     * @param array<string, Book> $byTitle
     */
    private static function book(CatalogProductItem $item, array $bySlug, array $byTitle): ?Book
    {
        $book = $bySlug[$item->productSlug] ?? $byTitle[self::normalize($item->productTitle)] ?? null;
        if (null === $book || !self::isEarlierVersion($item)) {
            return $book;
        }

        return $book->getPreviousVersion() ?? $book;
    }

    // The books by slug and by title, the latest version answering for a title several versions share
    /** @return array{array<string, Book>, array<string, Book>} */
    private function index(): array
    {
        $bySlug = [];
        $byTitle = [];
        foreach ($this->bookRepository->findBy(['isDeleted' => false]) as $book) {
            $bySlug[(string) $book->getSlug()] = $book;
            if (null === $book->getNewerVersion()) {
                $byTitle[self::normalize($book->getTitle())] = $book;
            }
        }

        return [$bySlug, $byTitle];
    }

    // The edition a kind of file belongs to, created when the book has none yet - a book sold as a file in the shop had no need of one before
    private function edition(Book $book, BookEditionFileKind $kind): BookEdition
    {
        $editionKind = BookEditionFileKind::Audio === $kind ? BookEditionKind::Audio->value : BookEditionKind::Digital->value;
        foreach ($book->getEditions() as $edition) {
            if ($editionKind === $edition->getKind()) {
                return $edition;
            }
        }

        $edition = new BookEdition()->setKind($editionKind);
        $book->addEdition($edition);
        $this->em->persist($edition);

        return $edition;
    }

    // The shop's file copied into a file of the edition's own, the source left where it is, at the shop's price - the edition then sold in the shop
    private function copy(CatalogProductItem $item, BookEdition $edition, BookEditionFileKind $kind): void
    {
        $file = new BookEditionFile()->setPrice($item->price);
        $file->setFile(new ReplacingFile($item->filePath));
        $edition->setFileOf($kind, $file)->setChannels([...$edition->getChannels(), BookChannel::Shop->value]);
        $this->em->persist($file);
    }

    // One kind of file of one edition, the edition taken as the very object this run reads or creates
    private static function slot(BookEdition $edition, BookEditionFileKind $kind): string
    {
        return spl_object_id($edition) . '-' . $kind->value;
    }

    private static function kind(CatalogProductItem $item): ?BookEditionFileKind
    {
        $extension = strtolower(pathinfo($item->filePath, \PATHINFO_EXTENSION));

        return match (true) {
            \in_array($extension, ['mp3', 'm4a'], true) => BookEditionFileKind::Audio,
            'epub' === $extension => BookEditionFileKind::Epub,
            'pdf' === $extension && (str_contains((string) $item->slug, 'livret') || str_contains((string) $item->slug, 'booklet')) => BookEditionFileKind::Booklet,
            'pdf' === $extension => BookEditionFileKind::Pdf,
            default => null,
        };
    }

    private static function isEarlierVersion(CatalogProductItem $item): bool
    {
        return str_contains((string) $item->slug, 'version-origin');
    }

    private static function normalize(?string $title): string
    {
        return new AsciiSlugger()->slug((string) $title)->lower()->toString();
    }
}
