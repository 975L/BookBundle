<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Media;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Twig\BookSectionsExtension;
use c975L\UiBundle\Contract\PlayDurationProviderInterface;

// How long a book's recording plays, read from the file by the server itself, for UiBundle to count a listen only once enough of it went by (see PlayTicket): what the page says could be anything
class BookPlayDurationProvider implements PlayDurationProviderInterface
{
    public function __construct(
        private readonly BookRepository $bookRepository,
        private readonly AudioDurationReader $durationReader,
    ) {
    }

    // Books only: the kind their favourites and listens are counted under
    public function supports(string $ownerType): bool
    {
        return BookFavoriteItemProvider::OWNER_TYPE === $ownerType;
    }

    // The book's mp3, the one format the reader measures and the one the listening page plays first. Null for a book not published, or with no mp3 to read
    public function getPlayDuration(string $ownerType, int $ownerId): ?int
    {
        $book = $this->bookRepository->findPublishedByIds([$ownerId])[0] ?? null;
        if (null === $book) {
            return null;
        }

        $audio = array_find(BookSectionsExtension::audioMedias($book), static fn (Media $media): bool => 'audio/mpeg' === $media->getMimeType());

        return null === $audio ? null : $this->durationReader->ofMedia($audio);
    }
}
