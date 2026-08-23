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
use c975L\UiBundle\Contract\FavoriteItemProviderInterface;
use c975L\UiBundle\Model\CollectionItem;
use Symfony\Component\Asset\Packages;

// Turns the "book" rows UiBundle holds a name and an id for back into something a page can name - a wishlist entry, and the heading of the page a reader leaves a review from (see c975L\UiBundle\Controller\ReviewController)
// The only place of this bundle that knows what that name stands for, which is the same word its ratings and its reviews are filed under
class BookFavoriteItemProvider implements FavoriteItemProviderInterface
{
    // The vocabulary this bundle files its books under, the same one the rating widget and BookCrudController already use
    public const string OWNER_TYPE = 'book';

    public function __construct(
        private readonly BookRepository $bookRepository,
        private readonly BookPublicUrlResolver $publicUrlResolver,
        private readonly Packages $packages,
    ) {
    }

    public function supports(string $ownerType): bool
    {
        return self::OWNER_TYPE === $ownerType;
    }

    public function getItems(string $ownerType, array $ownerIds): array
    {
        $items = [];

        // The repository already leaves out what a visitor may not reach - a book in the trash, one not published yet - so an id nobody may see is simply absent rather than named
        foreach ($this->bookRepository->findPublishedByIds($ownerIds) as $book) {
            $items[(int) $book->getId()] = new CollectionItem(
                title: trim((string) $book->getTitle()),
                description: $book->getSummary(),
                imageUrl: $this->coverUrl($book),
                // Null on a site not serving the books' route at all, which is what resolvePath() answers rather than throwing (see BookUrlExtension)
                url: $this->publicUrlResolver->resolvePath('book_display', ['slug' => $book->getSlug()]),
                slug: $book->getSlug(),
            );
        }

        return $items;
    }

    // What stands for the book wherever it is named, borrowed from the very function its own page and its cards read (see BookSectionsExtension::cover())
    private function coverUrl(Book $book): ?string
    {
        $cover = BookSectionsExtension::cover($book);

        return null === $cover || null === $cover->getName() ? null : $this->packages->getUrl($cover->getName());
    }
}
