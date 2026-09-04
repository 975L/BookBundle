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
use c975L\BookBundle\Entity\BookContributor;
use c975L\BookBundle\Entity\BookEdition;
use c975L\BookBundle\Repository\BookRepository;
use c975L\UiBundle\Service\UniqueSlug;
use Symfony\Component\String\Slugger\SluggerInterface;

// Publishing a new version of a book: the book keeps its address and its identity - its id, its slug, its readers' ratings, its blocks, its translations - and a twin takes the version being set aside, with its ISBN, its date, its files and its platforms. The twin only leaves the catalog: it keeps its page, its ISBN and its place in the sitemap, and is reached from the book replacing it (see Book::$newerVersion). The gesture sits here rather than in the screen or in a command, because it serves both: splitting an already published catalog, and publishing a new version of an existing book
class BookVersionPublisher
{
    // What the twin's slug carries on top of the book's. An English word because the bundle knows no site's language, and editable anyway: the slug is a field the editor corrects
    public const string SLUG_SUFFIX = 'previous';

    // The column's length, which the suffix must not overflow
    private const int SLUG_MAX_LENGTH = 100;

    public function __construct(
        private readonly BookRepository $bookRepository,
        private readonly SluggerInterface $slugger,
    ) {
    }

    // The twin carrying the version set aside: the same book, the same number, the same language, the same people credited. No translations, no blocks, no reader ratings - those belong to the page being kept, which is the one outside links reach. The title is the one the editor writes, in the book's language: the bundle presumes no reason for the replacement - original edition, unillustrated text, second edition - and that title is what names the cross link from one version to the other (see Book/Hero.html.twig)
    public function createPreviousVersion(Book $book, string $title, ?string $slug = null): Book
    {
        $now = new \DateTime();

        $previous = new Book()
            ->setTitle($title)
            ->setSlug($slug ?? $this->previousSlug($book))
            // The column is nullable, its setter is not
            ->setSummary((string) $book->getSummary())
            ->setAuthor($book->getAuthor())
            ->setIllustrator($book->getIllustrator())
            ->setPublished($book->getPublished())
            ->setSerie($book->getSerie())
            ->setNumber($book->getNumber())
            ->setAge($book->getAge())
            ->setLanguage($book->getLanguage())
            // The content belonging to the site follows the text, not the illustration: it is the same book
            ->setData($book->getData())
            ->setCreation($now)
            ->setModification($now)
            ->setNewerVersion($book);

        // Everyone else the book credits follows the text: the twin is the same story, read by the same voice and carried into another language by the same pen (see BookContributor)
        foreach ($book->getContributors() as $credit) {
            $previous->addContributor(new BookContributor()
                ->setContributor($credit->getContributor())
                ->setRole($credit->getRole())
                ->setPosition($credit->getPosition()));
        }

        return $previous;
    }

    // The address proposed for the twin. The book keeps its own: it is the one outside links and the search engines carry, and it must lead to the current version
    public function previousSlug(Book $book): string
    {
        $base = mb_substr((string) $book->getSlug(), 0, self::SLUG_MAX_LENGTH - mb_strlen(self::SLUG_SUFFIX) - 1);

        return UniqueSlug::build(
            $this->slugger,
            $base . '-' . self::SLUG_SUFFIX,
            fn (string $candidate): bool => null !== $this->bookRepository->findOneBy(['slug' => $candidate])
        );
    }

    // Moves an edition onto another book: it is what carries the ISBN and the release date of the text as it came out. Its files and its platforms do not follow it - they belong to the book, and the pages are what leave with it (see moveMedias()). Written on the owning side only, never through removeEdition(): the editions collection is "orphanRemoval: true" (see Book::$editions), and taking a row out of it schedules it for deletion - reattaching it elsewhere in the same unit of work does not undo that. The target book must therefore already exist in database
    public function moveEdition(BookEdition $edition, Book $target): void
    {
        $edition->setBook($target);
    }

    // What the version set aside takes with it: the pages leafed through and the flipbook animating them. They tell the text as it came out, and the book staying behind gets its own - the recording does not move, the text being the same from one version to the other. The owning side only, like moveEdition(): a book's collections are "orphanRemoval: true", and taking a row out of one schedules it for deletion
    /** @param list<string> $kinds */
    public function moveMedias(Book $source, Book $target, array $kinds): int
    {
        $moved = 0;

        foreach ($source->getMedias()->toArray() as $media) {
            if (\in_array((string) $media->getKind(), $kinds, true)) {
                $media->setBook($target);
                ++$moved;
            }
        }

        return $moved;
    }

    // The version of a book bearing that name, null when it publishes none under it
    public function editionOfKind(Book $book, string $kind): ?BookEdition
    {
        foreach ($book->getEditions() as $edition) {
            if ($kind === $edition->getKind()) {
                return $edition;
            }
        }

        return null;
    }
}
