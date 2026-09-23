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
use c975L\BookBundle\Twig\BookSectionsExtension;
use c975L\ConfigBundle\Service\SiteUrlResolver;
use c975L\UiBundle\Contract\SocialContentSourceInterface;
use c975L\UiBundle\Model\SocialContent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// Hands SocialBundle's publication the catalog's books, oldest published first - a site without SocialBundle simply never asks. What went out where is SocialBundle's to record
class BookSocialContentSource implements SocialContentSourceInterface
{
    // A book is worth recalling a season later, the catalog of a publisher changing slowly
    private const int REPEAT_AFTER_DAYS = 90;

    public function __construct(
        private readonly BookRepository $bookRepository,
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

    // The catalog as a visitor reads it (published, not replaced by a newer edition), oldest first so a back catalog goes out in the order it was written
    public function getNextContent(array $excludedIds): ?SocialContent
    {
        foreach (array_reverse($this->bookRepository->findAllPublished()) as $book) {
            if (!\in_array((string) $book->getId(), $excludedIds, true)) {
                $content = $this->toContent($book);
                if (null !== $content) {
                    return $content;
                }
            }
        }

        return null;
    }

    // Null for a book taken off the catalog since its post was prepared
    public function getContent(string $sourceId): ?SocialContent
    {
        $book = $this->bookRepository->find((int) $sourceId);

        return $book instanceof Book && $book->isShownInCatalog() && !$book->isToBePublished() ? $this->toContent($book) : null;
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
        );
    }
}
