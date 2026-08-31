<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Management;

use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Repository\ContributorRepository;
use c975L\BookBundle\Repository\SerieRepository;
use c975L\BookBundle\Repository\StripRepository;
use c975L\UiBundle\Contract\BlockOwnerResolverInterface;
use c975L\UiBundle\Contract\HasBlocksInterface;

// Lets BlockMoveController relocate a Book's, a Serie's, a Strip's or a Contributor's Block without depending on any of the four classes
class BookBlockOwnerResolver implements BlockOwnerResolverInterface
{
    // Shared with BookCrudController/SerieCrudController/StripCrudController's own blockMoveRowAttr() calls, so the owner-type strings only ever exist in one place
    public const TYPE_BOOK = 'book';
    public const TYPE_SERIE = 'serie';
    public const TYPE_STRIP = 'strip';
    public const TYPE_CONTRIBUTOR = 'contributor';

    private const array TYPES = [self::TYPE_BOOK, self::TYPE_SERIE, self::TYPE_STRIP, self::TYPE_CONTRIBUTOR];

    public function __construct(
        private readonly BookRepository $bookRepository,
        private readonly ContributorRepository $contributorRepository,
        private readonly SerieRepository $serieRepository,
        private readonly StripRepository $stripRepository,
    ) {
    }

    public function supports(string $ownerType): bool
    {
        return in_array($ownerType, self::TYPES, true);
    }

    public function find(string $ownerType, int $ownerId): ?HasBlocksInterface
    {
        return match ($ownerType) {
            self::TYPE_BOOK => $this->bookRepository->find($ownerId),
            self::TYPE_SERIE => $this->serieRepository->find($ownerId),
            self::TYPE_STRIP => $this->stripRepository->find($ownerId),
            self::TYPE_CONTRIBUTOR => $this->contributorRepository->find($ownerId),
            default => null,
        };
    }
}
