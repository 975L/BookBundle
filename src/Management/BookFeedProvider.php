<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Management;

use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\BookServiceInterface;
use c975L\BookBundle\Twig\BookSectionsExtension;
use c975L\ConfigBundle\Management\FeedProviderInterface;
use c975L\ConfigBundle\Service\SiteUrlResolver;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

// The latest books out (/feed/book.xml), for a reader who would rather not leave an e-mail for a release alert
class BookFeedProvider implements FeedProviderInterface
{
    public function __construct(
        private readonly BookServiceInterface $bookService,
        private readonly BookPublicUrlResolver $bookPublicUrlResolver,
        private readonly SiteUrlResolver $siteUrlResolver,
        private readonly TranslatorInterface $translator,
        private readonly RequestStack $requestStack,
    ) {
    }

    // Served at /feed/book.xml
    public function getFeedName(): string
    {
        return 'book';
    }

    // Title shown by a feed reader, in the language of the page announcing it
    public function getFeedTitle(): string
    {
        return $this->translator->trans('label.feed_books', [], 'book');
    }

    // Nothing when the site does not serve its book pages, BookPublicUrlResolver then giving no url (see BookRoutePrefix). The books of the language the feed declares, a catalog writing its translations as rows of their own listing each book once - every language outside a request
    public function getEntries(int $limit): array
    {
        $siteUrl = $this->siteUrlResolver->siteUrl();
        if (null === $siteUrl) {
            return [];
        }

        $entries = [];
        foreach ($this->bookService->findAllPublished($limit, $this->requestStack->getCurrentRequest()?->getLocale()) as $book) {
            $url = $this->bookPublicUrlResolver->resolve('book_display', ['slug' => $book->getSlug()]);
            if (null === $url) {
                return [];
            }

            $cover = BookSectionsExtension::cover($book)?->getName();
            $entries[] = [
                'url' => $url,
                'title' => (string) $book->getTitle(),
                'updated' => $book->getPublished() ?? $book->getModification() ?? new \DateTimeImmutable(),
                'summary' => $book->getSummary(),
                'image' => null === $cover ? null : $siteUrl . '/' . ltrim($cover, '/'),
            ];
        }

        return $entries;
    }
}
