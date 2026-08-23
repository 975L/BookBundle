<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Twig;

use c975L\BookBundle\Entity\Book;
use Twig\Attribute\AsTwigFunction;

// The other versions of one and the same text - the one newly illustrated, the one revised, the first one published. Each is a book of its own carrying its own ISBN (see Book::$newerVersion), and the chain is walked from whichever end the visitor arrived at, exactly as the translations are
class BookVersionExtension
{
    // Every other version, the more recent ones first and the older ones after: a reader landing on a superseded text is looking for the one that replaces it, and a reader on the current one is looking back
    // Named by nothing this bundle writes: a version is told from another by the title its editor gave it, in the book's own language, the way a translation is told by the endonym of its language
    /** @return list<Book> */
    #[AsTwigFunction('book_versions')]
    public static function versions(Book $book): array
    {
        $family = [...self::chain($book, static fn (Book $step): ?Book => $step->getNewerVersion())];
        $family = [...array_reverse($family), ...self::chain($book, static fn (Book $step): ?Book => $step->getPreviousVersion())];

        // A book in the trash is off the site: offered as a version, the link would send the reader straight to its 410
        return array_values(array_filter($family, static fn (Book $other): bool => !$other->isDeleted()));
    }

    // One side of the chain, step by step. Followed rather than read in one jump: a third version chains onto the second, and the guard is there for a chain closed by hand, which would spin the loop forever
    /**
     * @param callable(Book): ?Book $step
     *
     * @return list<Book>
     */
    private static function chain(Book $book, callable $step): array
    {
        $chain = [];
        $seen = [spl_object_id($book) => true];

        while (($next = $step($book)) instanceof Book && !isset($seen[spl_object_id($next)])) {
            $seen[spl_object_id($next)] = true;
            $chain[] = $next;
            $book = $next;
        }

        return $chain;
    }
}
