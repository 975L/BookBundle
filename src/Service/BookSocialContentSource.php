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
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Repository\SerieRepository;
use c975L\BookBundle\Twig\BookSectionsExtension;
use c975L\ConfigBundle\Service\SiteUrlResolver;
use c975L\UiBundle\Contract\BrowsableSocialContentSourceInterface;
use c975L\UiBundle\Contract\ScopedSocialContentSourceInterface;
use c975L\UiBundle\Contract\SocialContentSourceInterface;
use c975L\UiBundle\Model\SocialContent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// Hands SocialBundle's publication the catalog's books, oldest published first - a site without SocialBundle simply never asks. Its series are the groups a publication slot may be narrowed to, so one series of posts takes some of them and another the rest. What went out where is SocialBundle's to record
class BookSocialContentSource implements BrowsableSocialContentSourceInterface, ScopedSocialContentSourceInterface, SocialContentSourceInterface
{
    // A book is worth recalling a season later, the catalog of a publisher changing slowly
    private const int REPEAT_AFTER_DAYS = 90;

    public function __construct(
        private readonly BookRepository $bookRepository,
        private readonly SerieRepository $serieRepository,
        private readonly BookPublicUrlResolver $bookPublicUrlResolver,
        private readonly SiteUrlResolver $siteUrlResolver,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    public function getSourceType(): string
    {
        return 'book';
    }

    public function getRepeatAfterDays(): ?int
    {
        return self::REPEAT_AFTER_DAYS;
    }

    public function getNextContent(array $excludedIds): ?SocialContent
    {
        return $this->getNextScopedContent($excludedIds, []);
    }

    // The series of books a slot may pick among, the ones the books' index lists
    public function getScopes(): array
    {
        $scopes = [];
        foreach ($this->serieRepository->findWithBooks() as $serie) {
            $scopes[(string) $serie->getId()] = (string) $serie->getTitle();
        }

        return $scopes;
    }

    // The catalog as a visitor reads it (published, not replaced by a newer edition), oldest first so a back catalog goes out in the order it was written - only the books of the series given, a book of none left out then
    public function getNextScopedContent(array $excludedIds, array $scopeIds): ?SocialContent
    {
        foreach (array_reverse($this->bookRepository->findAllPublished()) as $book) {
            if (!\in_array((string) $book->getId(), $excludedIds, true) && $this->isInScope($book, $scopeIds)) {
                $content = $this->toContent($book);
                if (null !== $content) {
                    return $content;
                }
            }
        }

        return null;
    }

    // The books not posted yet, the latest first, for a post's content to be chosen among - in the series given, every one when none is
    public function findContents(array $excludedIds, array $scopeIds, int $limit): array
    {
        return array_values(array_filter(array_map($this->toContent(...), $this->bookRepository->findPostableLatest($excludedIds, $limit, array_map(intval(...), $scopeIds)))));
    }

    // The series a book is in, the one a post's book is drawn again from - null for a book of none
    public function getContentScope(string $sourceId): ?string
    {
        $serieId = $this->bookRepository->find((int) $sourceId)?->getSerie()?->getId();

        return null === $serieId ? null : (string) $serieId;
    }

    // Null for a book taken off the catalog since its post was prepared
    public function getContent(string $sourceId): ?SocialContent
    {
        $book = $this->bookRepository->find((int) $sourceId);

        return $book instanceof Book && $book->isShownInCatalog() && !$book->isToBePublished() ? $this->toContent($book) : null;
    }

    // Every book when no series is given
    /** @param list<string> $scopeIds */
    private function isInScope(Book $book, array $scopeIds): bool
    {
        return [] === $scopeIds || \in_array((string) $book->getSerie()?->getId(), $scopeIds, true);
    }

    // Null while the book has no public url - "site-url" unset, or the book pages not served on this site
    private function toContent(Book $book): ?SocialContent
    {
        $url = $this->bookPublicUrlResolver->resolve('book_display', ['slug' => $book->getSlug()]);
        if (null === $url) {
            return null;
        }

        $cover = BookSectionsExtension::cover($book)?->getName();

        return new SocialContent(
            sourceId: (string) $book->getId(),
            title: (string) $book->getTitle(),
            url: $url,
            imagePath: null === $cover ? null : $this->projectDir . '/public/' . $cover,
            imageUrl: null === $cover ? null : $this->siteUrlResolver->siteUrl() . '/' . $cover,
            imageAlt: (string) $book->getTitle(),
            variables: array_filter([
                'description' => trim(html_entity_decode(strip_tags((string) $book->getSummary()))),
                'serie' => (string) $book->getSerie()?->getTitle(),
            ]),
            locale: $book->getLanguage(),
        );
    }
}
