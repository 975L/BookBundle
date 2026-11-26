<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Controller\Management;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookColoring;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// A book's drawings to colour as an app will show them, those waiting for their day included, so whoever composes the book checks them before they come out, at the role its drawings are edited at: each with its image, title, day and crayons, grouped by level in the order the site names them
class BookColoringsPreviewController extends AbstractController
{
    // EasyAdmin prefixes the name with the Dashboard's own route name
    public const string ROUTE = 'management_book_colorings_preview';

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly BookCustomizationRegistry $customizationRegistry,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    // Kept out of search engines like every screen of the back office, the drawings shown there being not out yet
    #[AdminRoute(path: '/book/{id}/colorings/preview', name: 'book_colorings_preview')]
    public function preview(int $id): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        $book = $this->entityManager->getRepository(Book::class)->find($id);
        if (!$book instanceof Book) {
            throw new NotFoundHttpException();
        }

        $response = $this->render('@c975LBook/management/book_colorings_preview.html.twig', [
            'book' => $book,
            'groups' => $this->groups($book),
        ]);
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    // The drawings by level, the site's levels first in its order, then those carrying none or one it no longer names; a level holding none is left out
    /** @return list<array{kind: ?string, label: ?string, colorings: list<BookColoring>}> */
    public function groups(Book $book): array
    {
        $kinds = $this->customizationRegistry->getPrintableKinds();
        $colorings = $book->getColorings()->toArray();
        usort($colorings, static fn (BookColoring $a, BookColoring $b): int => $a->getPosition() <=> $b->getPosition());
        $byKind = [];
        foreach ($colorings as $coloring) {
            $byKind[isset($kinds[(string) $coloring->getKind()]) ? (string) $coloring->getKind() : ''][] = $coloring;
        }

        $groups = [];
        foreach ([...array_map(strval(...), array_keys($kinds)), ''] as $kind) {
            if (isset($byKind[$kind])) {
                $groups[] = ['kind' => '' === $kind ? null : $kind, 'label' => $kinds[$kind] ?? null, 'colorings' => $byKind[$kind]];
            }
        }

        return $groups;
    }
}
