<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookEdition;
use c975L\BookBundle\Enum\BookChannel;
use c975L\BookBundle\Enum\BookEditionFileKind;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Twig\BookSectionsExtension;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Storage\PrivateDirectory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

// What Google Play Books' crawler finds in the folders it is handed (see GooglePlayFeedController): the ebooks' ONIX under "onix/<collection>-rights/", their files and covers under "ebooks/<collection>/", each named the way Google matches them - alphanumeric, the ISBN standing for the book
class GooglePlayFeed
{
    private const string CACHE_KEY = 'book_google_ebooks';

    public function __construct(
        private readonly BookRepository $bookRepository,
        private readonly BookOnixBuilder $onixBuilder,
        private readonly ConfigServiceInterface $configService,
        private readonly TagAwareCacheInterface $cache,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
        #[Autowire(param: 'kernel.cache_dir')]
        private readonly string $cacheDir,
    ) {
    }

    // The collection code Google gave the publisher, empty while the feed is not set up
    public function collection(): string
    {
        return preg_replace('/[^A-Za-z0-9]/', '', (string) $this->configService->get('book-google-collection')) ?? '';
    }

    // The single ONIX file, named after the publisher and the day the catalog last changed: a new name is what tells the crawler there is something to fetch again
    /** @return array{name: string, content: string, modified: \DateTimeImmutable} */
    public function onix(string $baseUrl): array
    {
        $books = $this->bookRepository->findAllForOnix();
        $publisher = BookOnixBuilder::publisher($this->configService);
        $content = $this->onixBuilder->build($books, $publisher, $baseUrl, self::sent(...));
        $modified = $this->lastModified($books);

        return [
            'name' => preg_replace('/[^A-Za-z0-9]/', '', new AsciiSlugger()->slug($publisher)->toString()) . '_' . $modified->format('Ymd') . '.xml',
            'content' => $content,
            'modified' => $modified,
        ];
    }

    // Each ebook's own files and its front cover, keyed by the name the crawler asks for - "<ISBN>.epub", "<ISBN>_interior.pdf", "<ISBN>_frontcover.jpg" - only those on disk. The booklet stays home: it is printed by a buyer, not read in a store. Kept until the catalog changes, the crawler asking for every file in turn
    /** @return array<string, array{path: string, modified: \DateTimeImmutable}> */
    public function ebooks(): array
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
            $item->tag([BookBlockCacheInvalidator::CACHE_TAG_CATALOG]);

            return $this->collectEbooks();
        });
    }

    /** @return array<string, array{path: string, modified: \DateTimeImmutable}> */
    private function collectEbooks(): array
    {
        $files = [];
        foreach ($this->bookRepository->findAllForOnix() as $book) {
            foreach ($book->getEditions() as $edition) {
                $isbn = preg_replace('/\D/', '', (string) $edition->getIsbn()) ?? '';
                if (13 !== \strlen($isbn) || !self::sent($edition)) {
                    continue;
                }

                $sent = $this->editionFiles($edition, $isbn);
                if ([] !== $sent) {
                    $files += $sent;
                    $this->addCover($files, $book, $isbn);
                }
            }
        }

        return $files;
    }

    // The edition's EPUB and PDF found on disk, under the names Google matches them by
    /** @return array<string, array{path: string, modified: \DateTimeImmutable}> */
    private function editionFiles(BookEdition $edition, string $isbn): array
    {
        $files = [];
        foreach ([BookEditionFileKind::Epub->value => $isbn . '.epub', BookEditionFileKind::Pdf->value => $isbn . '_interior.pdf'] as $kind => $name) {
            $file = $edition->getFileOf(BookEditionFileKind::from($kind));
            if (null === $file?->getName()) {
                continue;
            }

            $path = $this->projectDir . '/' . PrivateDirectory::resolve($file) . '/' . $file->getName();
            if (is_file($path)) {
                $files[$name] = ['path' => $path, 'modified' => $file->getUpdatedAt() ?? new \DateTimeImmutable('@' . filemtime($path))];
            }
        }

        return $files;
    }

    // What Google is sent: the ebooks ticked "Google", neither printed nor recorded
    private static function sent(BookEdition $edition): bool
    {
        return $edition->hasChannel(BookChannel::Google) && BookOnixBuilder::isEbook($edition);
    }

    // The cover the pages show, handed over as a JPEG since Google takes no WebP - converted once into the cache directory, again only when the cover changes
    /** @param array<string, array{path: string, modified: \DateTimeImmutable}> $files */
    private function addCover(array &$files, Book $book, string $isbn): void
    {
        $cover = BookSectionsExtension::cover($book);
        $path = $this->projectDir . '/public/' . ltrim((string) $cover?->getName(), '/');
        if (null === $cover?->getName() || !is_file($path)) {
            return;
        }

        $modified = (int) filemtime($path);
        if (!\in_array(strtolower(pathinfo($path, \PATHINFO_EXTENSION)), ['jpg', 'jpeg'], true)) {
            $path = $this->jpeg($path, $isbn, $modified);
        }

        if (null !== $path) {
            $files[$isbn . '_frontcover.jpg'] = ['path' => $path, 'modified' => new \DateTimeImmutable('@' . $modified)];
        }
    }

    // The cover re-encoded as a JPEG at the quality the stores' guides ask for, null when GD cannot read it
    private function jpeg(string $source, string $isbn, int $modified): ?string
    {
        $path = $this->cacheDir . '/book-google/' . $isbn . '_frontcover.jpg';
        if (is_file($path) && filemtime($path) >= $modified) {
            return $path;
        }

        $image = imagecreatefromstring((string) file_get_contents($source));
        if (false === $image) {
            return null;
        }

        new Filesystem()->mkdir(\dirname($path));

        return imagejpeg($image, $path, 90) ? $path : null;
    }

    // The latest change among the books sent to Google - the book itself, or one of the files of its editions sent - their release date standing in for a book never edited since
    /** @param Book[] $books */
    private function lastModified(array $books): \DateTimeImmutable
    {
        $latest = new \DateTimeImmutable('@0');
        foreach ($books as $book) {
            $sent = array_filter($book->getEditions()->toArray(), self::sent(...));
            if ([] === $sent) {
                continue;
            }

            $dates = [$book->getModification() ?? $book->getPublished()];
            foreach ($sent as $edition) {
                foreach ($edition->getFiles() as $file) {
                    $dates[] = $file->getUpdatedAt();
                }
            }

            foreach ($dates as $date) {
                if (null !== $date && $date > $latest) {
                    $latest = \DateTimeImmutable::createFromInterface($date);
                }
            }
        }

        return $latest;
    }
}
