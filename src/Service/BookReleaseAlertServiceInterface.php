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
use c975L\BookBundle\Entity\BookReleaseAlert;

interface BookReleaseAlertServiceInterface
{
    // Puts an address on the waiting list of a book still to come and acknowledges it by e-mail; false when the book is already out, and false when the catalog no longer carries it - set aside, replaced, or held by a serie set aside - the nightly run reading those same conditions, nothing being stored and no e-mail sent
    public function subscribe(Book $book, string $email, string $locale): bool;

    // Drops the subscription, the button of the page the confirmation e-mail's link opens leading here
    public function unsubscribe(BookReleaseAlert $releaseAlert): void;

    // Tells the visitors waiting on a book that it is out, at most $limit of them in one run
    /** @return int how many were told */
    public function notifyReleased(int $limit): int;

    // How many subscriptions are still waiting, all of them being pending until the parution deletes them
    public function countPending(): int;

    // Drops the waiting lists of books announced long ago and never published
    /** @return int how many were deleted */
    public function purgeStale(): int;
}
