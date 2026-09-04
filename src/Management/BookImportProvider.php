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
use c975L\BookBundle\Entity\BookContributor;
use c975L\BookBundle\Entity\BookEdition;
use c975L\BookBundle\Entity\BookLink;
use c975L\BookBundle\Entity\BookMarketing;
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Entity\BookPresse;
use c975L\BookBundle\Entity\BookVideo;
use c975L\BookBundle\Repository\BookRepository;
use c975L\ConfigBundle\Management\ImportProviderInterface;
use c975L\UiBundle\Management\BlockDataImporter;
use Doctrine\ORM\EntityManagerInterface;

// Imports a "book_book" content export (see BookExportProvider) - matches by slug, which is what a book answers at, and never by the exported id. Every child row is written over rather than dropped and built anew, each on the one natural key it has: a version by its kind, a platform by the version and the kind it sells for, a file by the name it is served under. That is what keeps a re-import from rewriting the whole catalog on disk, and what a version's files depend on - dropping a version takes them with it (see BookEdition::$medias)
class BookImportProvider implements ImportProviderInterface
{
    public const KIND = 'book_book';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BookRepository $bookRepository,
        private readonly BlockDataImporter $blockDataImporter,
        private readonly MediaArchiver $mediaArchiver,
        private readonly ContributorResolver $contributorResolver,
        private readonly BookCategoryResolver $categoryResolver,
        private readonly SerieResolver $serieResolver,
    ) {
    }

    public function supportsImport(string $kind): bool
    {
        return self::KIND === $kind;
    }

    public function import(array $items, ?string $filesDir = null): array
    {
        $created = 0;
        $updated = 0;
        $written = [];
        $series = [];
        // The people written by this very run, keyed by name, for the same reason the series are (see ContributorResolver)
        $contributors = [];
        // The categories written by this very run, keyed by slug, for the reason the series are (see BookCategoryResolver)
        $categories = [];

        // The books written by this very run, keyed by slug: what the translations are resolved against below, findOneBy() not seeing a book persisted but not yet flushed
        $books = [];

        foreach ($items as $item) {
            $book = $this->bookRepository->findOneBy(['slug' => $item['slug']]);
            $isNew = null === $book;
            $book ??= new Book();

            $this->fillBook($book, $item, $series, $contributors, $categories);
            $this->replaceBlocks($book, $item['blocks'] ?? [], $filesDir);

            $this->syncEditions($book, $item['editions'] ?? []);
            $this->syncContributors($book, $item['contributors'] ?? [], $contributors);
            $written = [...$written, ...$this->syncMedias($book, $item)];
            $this->syncLinks($book, $item['links'] ?? []);

            $this->em->persist($book);
            $books[$item['slug']] = $book;
            $isNew ? $created++ : $updated++;
        }

        $this->linkBooks($items, $books);

        $this->em->flush();

        $this->mediaArchiver->restoreFiles($written, $filesDir);

        return ['created' => $created, 'updated' => $updated];
    }

    // A second pass, the translated book being a book like any other: the archive lists the two in whichever order, and the one named may not have been read yet when the row naming it was
    /** @param array<string, Book> $books */
    private function linkBooks(array $items, array $books): void
    {
        foreach ($items as $item) {
            $this->linkTranslation($books[$item['slug']], $item['translationBook'] ?? null, $books);
            $this->linkNewerVersion($books[$item['slug']], $item['newerVersion'] ?? null, $books);
        }
    }

    /**
     * @param array<string, \c975L\BookBundle\Entity\Serie>        $series
     * @param array<string, \c975L\BookBundle\Entity\Contributor>  $contributors
     * @param array<string, \c975L\BookBundle\Entity\BookCategory> $categories
     */
    private function fillBook(Book $book, array $item, array &$series, array &$contributors, array &$categories): void
    {
        $this->fillBookIdentity($book, $item);
        $this->fillBookContributors($book, $item, $contributors);
        $this->fillBookDates($book, $item);
        $this->fillBookCrowdfunding($book, $item);
        $this->fillBookPublication($book, $item);

        $book->setSerie($this->serieResolver->resolve($item['serie'] ?? null, $item['serieTitle'] ?? null, $series));

        $this->fillBookCategories($book, $item, $categories);
    }

    // What the book is about, replaced whole rather than merged: an archive says which categories the book carries, and one taken off it there must come off it here too. Absent from an archive written before they existed, and read there as none
    /** @param array<string, \c975L\BookBundle\Entity\BookCategory> $categories */
    private function fillBookCategories(Book $book, array $item, array &$categories): void
    {
        foreach ($book->getCategories()->toArray() as $current) {
            $book->removeCategory($current);
        }

        foreach ($this->categoryResolver->resolveAll((array) ($item['categories'] ?? []), $categories) as $category) {
            $book->addCategory($category);
        }
    }

    // What the book is, everything but the two keys optional: an archive predating one of them imports a book that simply doesn't say
    private function fillBookIdentity(Book $book, array $item): void
    {
        $book
            ->setSlug($item['slug'])
            ->setTitle($item['title'])
            ->setSummary($item['summary'] ?? '')
            ->setAge($item['age'] ?? null)
            ->setNumber($item['number'] ?? null)
            ->setLanguage($item['language'] ?? null);
    }

    // The two people a book credits, named as they were when they were two strings: the resolver turns each name into the row it stands for, creating it when this environment doesn't hold it yet
    /** @param array<string, \c975L\BookBundle\Entity\Contributor> $contributors */
    private function fillBookContributors(Book $book, array $item, array &$contributors): void
    {
        $book
            ->setAuthor($this->contributorResolver->resolve($item['author'] ?? null, $item['authorWebsite'] ?? null, $contributors))
            ->setIllustrator($this->contributorResolver->resolve($item['illustrator'] ?? null, $item['illustratorWebsite'] ?? null, $contributors));
    }

    // The parts held by a row, written over on the pair they are named by - the person and the role, which is what names one credit within its book. A credit the archive no longer holds is dropped, the row saying nothing on its own
    /** @param array<string, \c975L\BookBundle\Entity\Contributor> $contributors */
    private function syncContributors(Book $book, array $creditsData, array &$contributors): void
    {
        $existing = [];
        foreach ($book->getContributors() as $credit) {
            $existing[$credit->getContributor()?->getName() . '|' . $credit->getRole()] = $credit;
        }

        $written = [];
        foreach ($creditsData as $creditData) {
            $key = $this->writeCredit($book, $creditData, $existing, $contributors);

            if (null !== $key) {
                $written[$key] = true;
            }
        }

        foreach ($existing as $key => $credit) {
            if (!isset($written[$key])) {
                $book->removeContributor($credit);
            }
        }
    }

    // One credit of the archive written onto the book, the pair naming it answering back so the parts no longer held can be dropped; null when the row names nobody or no part, the pair being the only key it has
    /**
     * @param array<string, BookContributor>                      $existing
     * @param array<string, \c975L\BookBundle\Entity\Contributor> $contributors
     */
    private function writeCredit(Book $book, array $creditData, array $existing, array &$contributors): ?string
    {
        $contributor = $this->contributorResolver->resolve($creditData['name'] ?? null, $creditData['website'] ?? null, $contributors);
        $role = (string) ($creditData['role'] ?? '');

        if (null === $contributor || '' === $role) {
            return null;
        }

        $key = $contributor->getName() . '|' . $role;
        $credit = $existing[$key] ?? new BookContributor()
            ->setContributor($contributor)
            ->setRole($role);
        $credit->setPosition($creditData['position'] ?? 0);

        $this->em->persist($credit);
        $book->addContributor($credit);

        return $key;
    }

    // The three dates the book carries, each read from the archive when it holds one
    private function fillBookDates(Book $book, array $item): void
    {
        $book
            ->setPublished(isset($item['published']) ? new \DateTime($item['published']) : null)
            // Both columns are required, so an archive predating them dates the book from the import rather than leaving it unwritten
            ->setCreation(isset($item['creation']) ? new \DateTime($item['creation']) : new \DateTime())
            ->setModification(isset($item['modification']) ? new \DateTime($item['modification']) : new \DateTime());
    }

    // The campaign, if the book ever ran one: the deadline means nothing without it, hence the pair kept together
    private function fillBookCrowdfunding(Book $book, array $item): void
    {
        $book
            ->setCrowdfunding($item['crowdfunding'] ?? null)
            ->setCrowdfundingEndDate(isset($item['crowdfundingEndDate']) ? new \DateTime($item['crowdfundingEndDate']) : null);
    }

    // Where the book stands rather than what it says: a round-trip must not put back on the site what an admin had taken off it
    private function fillBookPublication(Book $book, array $item): void
    {
        $book
            // What the site added to this book of its own, put back whole
            ->setData($item['data'] ?? null)
            // Optional like the rest, an archive predating the trash importing as a book that is not in it - which is what such an archive describes
            ->setIsDeleted($item['isDeleted'] ?? false)
            // Absent from every archive written before the flag existed, and read there as "shown" - the same thing the column's own default says
            ->setHidden($item['hidden'] ?? false);
    }

    // Existing Blocks have no natural key to match the imported ones against, so the whole collection is replaced - BlockRemovalListener removes the orphaned rows (and their Medias) on flush, same as PageImportProvider
    private function replaceBlocks(Book $book, array $blocksData, ?string $filesDir): void
    {
        foreach ($book->getBlocks()->toArray() as $existingBlock) {
            $book->removeBlock($existingBlock);
        }

        foreach ($this->blockDataImporter->buildBlocks($blocksData, $filesDir) as $block) {
            $book->addBlock($block);
        }
    }

    // One link written over, whether it was already on the book or is joining it now
    private function writeLink(Book $book, BookLink $link, array $linkData): void
    {
        $link
            ->setKind($linkData['kind'] ?? null)
            ->setUrl($linkData['url'] ?? null)
            ->setPosition($linkData['position'] ?? 0);

        $this->em->persist($link);
        $book->addLink($link);
    }

    // The versions written over on their kind, which is what names one within its book. A version the archive no longer holds is dropped last, after the files have been re-bound: dropping it takes whatever still hangs off it (see BookEdition::$medias)
    /** @return array<string, BookEdition> keyed by kind, for the files and the platforms to name theirs */
    private function syncEditions(Book $book, array $editionsData): array
    {
        $existing = [];
        foreach ($book->getEditions() as $edition) {
            $existing[(string) $edition->getKind()] = $edition;
        }

        $editions = [];
        foreach ($editionsData as $editionData) {
            $kind = (string) ($editionData['kind'] ?? '');
            $editions[$kind] = $this->writeEdition($book, $existing[$kind] ?? new BookEdition(), $kind, $editionData);
        }

        foreach ($existing as $kind => $edition) {
            if (!isset($editions[$kind])) {
                $book->removeEdition($edition);
            }
        }

        return $editions;
    }

    // One version written over, whether it was already on the book or is joining it now
    private function writeEdition(Book $book, BookEdition $edition, string $kind, array $editionData): BookEdition
    {
        $edition
            ->setKind($kind)
            ->setIsbn($editionData['isbn'] ?? null)
            ->setPages($editionData['pages'] ?? null)
            ->setFormat($editionData['format'] ?? null)
            ->setPosition($editionData['position'] ?? 0);

        $this->em->persist($edition);
        $book->addEdition($edition);

        return $edition;
    }

    // The book's five families of files, each written over on the name it is served under (see MediaArchiver::sync()) - a version's own files then bound back to it by its kind
    /**
     * @return list<array{0: \c975L\BookBundle\Entity\Media, 1: array}>
     */
    private function syncMedias(Book $book, array $item): array
    {
        $written = $this->mediaArchiver->sync(
            $book->getMedias(),
            $item['medias'] ?? [],
            static fn (): BookMedia => new BookMedia(),
            $book->addMedia(...),
            $book->removeMedia(...),
        );

        return [
            ...$written,
            ...$this->mediaArchiver->sync($book->getVideos(), $item['videos'] ?? [], static fn (): BookVideo => new BookVideo(), $book->addVideo(...), $book->removeVideo(...)),
            ...$this->mediaArchiver->sync($book->getPresses(), $item['presses'] ?? [], static fn (): BookPresse => new BookPresse(), $book->addPresse(...), $book->removePresse(...)),
            ...$this->mediaArchiver->sync($book->getMarketings(), $item['marketings'] ?? [], static fn (): BookMarketing => new BookMarketing(), $book->addMarketing(...), $book->removeMarketing(...)),
        ];
    }

    // The platforms overwritten on their kind, a book having one address per platform - what the archive no longer holds is detached, orphanRemoval deleting the row on flush
    private function syncLinks(Book $book, array $linksData): void
    {
        $existing = [];
        foreach ($book->getLinks() as $link) {
            $existing[(string) $link->getKind()] = $link;
        }

        $kept = [];
        foreach ($linksData as $linkData) {
            $key = (string) ($linkData['kind'] ?? '');
            $this->writeLink($book, $existing[$key] ?? new BookLink(), $linkData);
            $kept[$key] = true;
        }

        foreach ($existing as $key => $link) {
            if (!isset($kept[$key])) {
                $book->removeLink($link);
            }
        }
    }

    // The book this one translates, taken from what the run has just written first and from the site's own catalog otherwise - null for an archive naming a book neither holds, which is what a partial selection carries
    /** @param array<string, Book> $books */
    private function linkTranslation(Book $book, ?string $slug, array $books): void
    {
        $book->setTranslationBook(
            null === $slug || $slug === $book->getSlug()
                ? null
                : $books[$slug] ?? $this->bookRepository->findOneBy(['slug' => $slug])
        );
    }

    // Resolved in the same second pass and against the same map: the book that replaces this one is a book of the archive like any other, listed before or after it
    /** @param array<string, Book> $books */
    private function linkNewerVersion(Book $book, ?string $slug, array $books): void
    {
        $book->setNewerVersion(
            null === $slug || $slug === $book->getSlug()
                ? null
                : $books[$slug] ?? $this->bookRepository->findOneBy(['slug' => $slug])
        );
    }
}
