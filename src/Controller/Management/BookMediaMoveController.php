<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Controller\Management;

use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Service\BookMediaMoveRowAttrBuilder;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

// Moves a file of a book from one collection to another, as UiBundle's drag-and-drop asks (see its assets/js/ea-sortable.js): the row is never rewritten in the browser, its id is posted here and the page reloads - which is what lets two collections of different shapes swap rows, an edition row and an extract row not holding the same fields
class BookMediaMoveController extends AbstractController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[AdminRoute(
        path: '/book/media/move',
        name: 'book_media_move',
        options: ['methods' => ['POST']]
    )]
    public function move(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        if (!$this->isCsrfTokenValid(BookMediaMoveRowAttrBuilder::ROUTE, $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['error' => 'invalid_csrf'], 419);
        }

        $media = $this->entityManager->getRepository(BookMedia::class)->find((int) $request->request->get('id'));
        if (!$media instanceof BookMedia) {
            return new JsonResponse(['error' => 'unknown_media'], 404);
        }

        // The file stays in its own book: a collection of another book is no target, whatever the browser sent
        if ($media->getBook()?->getId() !== (int) $request->request->get('ownerId')) {
            return new JsonResponse(['error' => 'media_not_owned'], 403);
        }

        $error = $this->relocate($media, (string) $request->request->get('target'));
        if (null !== $error) {
            return new JsonResponse(['error' => $error], 400);
        }

        $this->entityManager->flush();
        $this->addFlash('success', $this->translator->trans('flash.media_moved', [], 'book'));

        return new JsonResponse(['moved' => true]);
    }

    // The target says where the file lands, and both belong to the book: what is leafed through before buying, and what runs through the slider. An edition is no target any more: it carries only what the book comes out under, its files belonging to the book (see BookEditionType)
    private function relocate(BookMedia $media, string $target): ?string
    {
        if (BookMediaMoveRowAttrBuilder::TARGET_FLIPBOOK !== $target && BookMediaMoveRowAttrBuilder::TARGET_EXTRACT !== $target) {
            return 'unknown_target';
        }

        $media->setKind($target);

        return null;
    }
}
