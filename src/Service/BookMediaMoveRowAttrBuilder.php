<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The attributes UiBundle's ea-sortable.js reads to drag a file from one collection to another - from the flipbook to the extracts, from an edition to the book. Same shape as BlockMoveRowAttrBuilder, whose mechanism this is: the collections of one group swap their rows, and the group names the address that moves them
class BookMediaMoveRowAttrBuilder
{
    public const string ROUTE = 'management_book_media_move';

    // The sorting group of a book's file collections
    public const string GROUP = 'book-media';

    // The target of a collection, what the controller receives and reads (see BookMediaMoveController)
    public const string TARGET_FLIPBOOK = 'flipbook';

    public const string TARGET_EXTRACT = 'extract';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // Empty for a book not saved yet - nothing to drag anywhere - and for a route this version doesn't declare: the sorting then stays off rather than the screen breaking
    /** @return array<string, string|int> */
    public function build(?int $bookId, string $target): array
    {
        if (null === $bookId) {
            return [];
        }

        try {
            $url = $this->urlGenerator->generate(self::ROUTE);
        } catch (RouteNotFoundException) {
            return [];
        }

        return [
            'data-ui-sort-group' => self::GROUP,
            'data-ui-move-owner-type' => 'book',
            'data-ui-move-owner-id' => $bookId,
            'data-ui-move-target' => $target,
            'data-ui-move-url' => $url,
            'data-ui-move-csrf-token' => $this->csrfTokenManager->getToken(self::ROUTE)->getValue(),
            'data-ui-move-failed-label' => $this->translator->trans('flash.media_move_failed', [], 'book'),
            'data-ui-move-close-label' => $this->translator->trans('action.close', [], 'ui'),
        ];
    }
}
