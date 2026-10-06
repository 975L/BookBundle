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
use c975L\BookBundle\Entity\BookEditionFile;
use c975L\BookBundle\Enum\BookChannel;
use c975L\BookBundle\Twig\BookSectionsExtension;
use c975L\UiBundle\Contract\ProductCatalogWriterInterface;
use c975L\UiBundle\Model\CatalogProduct;
use c975L\UiBundle\Model\CatalogProductItem;
use c975L\UiBundle\Storage\PrivateDirectory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

// Writes a book into the site's shop when one is installed (see ProductCatalogWriterInterface): one product per book, holding the files of every edition ticked "Shop", its earlier versions' too - the shop sells the text as it first came out beside the one rewritten, as it always has
class BookShopPublisher
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
        private readonly ?ProductCatalogWriterInterface $writer = null,
    ) {
    }

    // Whether a shop is there to write to
    public function isAvailable(): bool
    {
        return null !== $this->writer;
    }

    // The book's whole family of versions, written as the product of its latest - left alone while an edition ticked "Shop" has no file on disk yet, which would otherwise set every item of the product aside
    public function publish(Book $book): void
    {
        if (null === $this->writer) {
            return;
        }

        $latest = $book->getLatestVersion();
        $items = [];
        $sold = false;
        for ($version = $latest; null !== $version; $version = $version->getPreviousVersion()) {
            foreach ($version->getEditions() as $edition) {
                if ($edition->hasChannel(BookChannel::Shop)) {
                    $sold = true;
                    $items = [...$items, ...$this->items($edition, $version !== $latest)];
                }
            }
        }

        if ($sold && [] === $items) {
            return;
        }

        $cover = BookSectionsExtension::cover($latest);
        $this->writer->write(new CatalogProduct(
            key: self::productKey($latest),
            title: (string) $latest->getTitle(),
            description: (string) $latest->getSummary(),
            items: $items,
            coverPath: null === $cover?->getName() ? null : $this->projectDir . '/public/' . ltrim($cover->getName(), '/'),
        ));
    }

    // The name the shop finds the book's product under
    public static function productKey(Book $book): string
    {
        return 'book-' . $book->getId();
    }

    // The name the shop finds one file's item under
    public static function itemKey(BookEdition $edition, string $fileKind): string
    {
        return 'book-' . $edition->getBook()?->getId() . '-' . $edition->getKind() . '-' . $fileKind;
    }

    // The edition's files found on disk, an earlier version's named as such
    /** @return list<CatalogProductItem> */
    private function items(BookEdition $edition, bool $earlierVersion): array
    {
        $items = [];
        foreach ($edition->getFiles() as $file) {
            $path = $this->projectDir . '/' . PrivateDirectory::resolve($file) . '/' . $file->getName();
            if (null === $file->getName() || !is_file($path)) {
                continue;
            }

            $items[] = new CatalogProductItem(
                key: self::itemKey($edition, (string) $file->getKind()),
                title: $this->title($file, $earlierVersion),
                filePath: $path,
                price: $file->getPrice(),
                currency: $edition->getCurrency(),
            );
        }

        return $items;
    }

    private function title(BookEditionFile $file, bool $earlierVersion): string
    {
        $title = $this->translator->trans('label.edition_file_' . $file->getKind(), [], 'book');

        return $earlierVersion ? $this->translator->trans('label.edition_file_earlier_version', ['%title%' => $title], 'book') : $title;
    }
}
