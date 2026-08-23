<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Management;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookEdition;
use c975L\BookBundle\Entity\BookLink;
use c975L\BookBundle\Entity\Media;
use c975L\BookBundle\Repository\BookRepository;
use c975L\ConfigBundle\Management\ExportProviderInterface;
use c975L\UiBundle\Management\BlockDataExporter;

// Serializes the books - their versions, the platforms that sell them, their covers, extracts, videos, press clippings and marketing files, real files bundled in the archive - into the shape ContentExporter/BookImportProvider expect
// The serie and the translated book a row names travel as slugs rather than as ids, which never match between two environments, and each under its own kind
class BookExportProvider implements ExportProviderInterface
{
    public function __construct(
        private readonly BookRepository $bookRepository,
        private readonly BlockDataExporter $blockDataExporter,
        private readonly MediaArchiver $mediaArchiver,
    ) {
    }

    public function getKind(): string
    {
        return BookImportProvider::KIND;
    }

    public function exportAll(): array
    {
        // findBy([]) rather than a published-only query: the archive is a faithful copy, a book exported out of the trash coming back to the trash rather than onto the site
        return $this->serialize($this->bookRepository->findBy([]));
    }

    // The rows an admin checked on the catalog's index, serialized exactly as the whole catalog is - what the "export selection" action of the crud screen hands to ContentExporter (see Controller\Management\Trait\TrashableCrudTrait::exportSelection())
    // @param list<int> $ids
    public function serializeIds(array $ids): array
    {
        return $this->serialize($this->bookRepository->findBy(['id' => $ids]));
    }

    // @param iterable<Book> $books
    public function serialize(iterable $books): array
    {
        $files = [];
        $items = [];
        foreach ($books as $book) {
            $items[] = $this->exportBookData($book, $files);
        }

        return ['items' => $items, 'files' => $files];
    }

    private function exportBookData(Book $book, array &$files): array
    {
        return [
            'slug' => $book->getSlug(),
            'title' => $book->getTitle(),
            'author' => $book->getAuthor(),
            'authorWebsite' => $book->getAuthorWebsite(),
            'illustrator' => $book->getIllustrator(),
            'illustratorWebsite' => $book->getIllustratorWebsite(),
            'summary' => $book->getSummary(),
            'published' => $book->getPublished()?->format(\DateTimeInterface::ATOM),
            'creation' => $book->getCreation()?->format(\DateTimeInterface::ATOM),
            'modification' => $book->getModification()?->format(\DateTimeInterface::ATOM),
            'age' => $book->getAge(),
            'number' => $book->getNumber(),
            'language' => $book->getLanguage(),
            'crowdfunding' => $book->getCrowdfunding(),
            'crowdfundingEndDate' => $book->getCrowdfundingEndDate()?->format(\DateTimeInterface::ATOM),
            // What this site adds to a book on top of what the bundle holds (see BookCustomizationProviderInterface) - a free-form payload, carried as it is
            'data' => $book->getData(),
            'isDeleted' => $book->isDeleted(),
            'serie' => $book->getSerie()?->getSlug(),
            'serieTitle' => $book->getSerie()?->getTitle(),
            // The book this one translates, named by what it answers at - resolved in a second pass on the way back in, the two rows being imported in whichever order the archive lists them
            'translationBook' => $book->getTranslationBook()?->getSlug(),
            // The book that replaces this one, named the same way and resolved in the same second pass (see Book::$newerVersion)
            'newerVersion' => $book->getNewerVersion()?->getSlug(),
            'blocks' => $this->blockDataExporter->exportBlocks($book->getBlocks(), $files),
            'editions' => array_map($this->exportEditionData(...), $book->getEditions()->toArray()),
            // Flat rather than nested inside the versions they belong to: a link and a file both name their version by kind, and the ones a book carries as a whole - its covers, its backdrop - name none
            'links' => array_map($this->exportLinkData(...), $book->getLinks()->toArray()),
            'medias' => $this->exportMedias($book->getMedias(), $files),
            'videos' => $this->exportMedias($book->getVideos(), $files),
            'presses' => $this->exportMedias($book->getPresses(), $files),
            'marketings' => $this->exportMedias($book->getMarketings(), $files),
        ];
    }

    // @param iterable<Media> $medias
    private function exportMedias(iterable $medias, array &$files): array
    {
        $data = [];
        foreach ($medias as $media) {
            $mediaData = $this->mediaArchiver->export($media, $files);
            if (null !== $mediaData) {
                $data[] = $mediaData;
            }
        }

        return $data;
    }

    private function exportEditionData(BookEdition $edition): array
    {
        return [
            'kind' => $edition->getKind(),
            'isbn' => $edition->getIsbn(),
            'pages' => $edition->getPages(),
            'format' => $edition->getFormat(),
            'position' => $edition->getPosition(),
        ];
    }

    private function exportLinkData(BookLink $link): array
    {
        return [
            'kind' => $link->getKind(),
            // The whole address, as it is stored: the affiliate identifier, the country and the anchor a platform hands over are part of it and deducible from nothing (see BookLink)
            'url' => $link->getUrl(),
            'position' => $link->getPosition(),
        ];
    }
}
