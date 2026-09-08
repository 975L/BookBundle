<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Management;

use c975L\BookBundle\Controller\Management\BookCrudController;
use c975L\BookBundle\Entity\BookMarketing;
use c975L\BookBundle\Entity\BookPresse;
use c975L\BookBundle\Repository\MediaRepository;
use c975L\UiBundle\Contract\PdfDocumentSourceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

// Hands this bundle's PDF documents to UiBundle's thumbnail check, which reads its own library and would otherwise see none of them: a press kit and a marketing sheet are rows of book_media, not of site_media. Failing to produce a thumbnail is silent by design (see VichPdfThumbnailListener) and the page falls back to a placeholder, so without this the whole catalog can lose its thumbnails without a single row saying so - which is exactly what happened when the migration swept the files no row named
class BookPdfDocumentSource implements PdfDocumentSourceInterface
{
    // Which collection of a book's screen the document is uploaded in, so the dashboard's pencil opens the right tab rather than the top of the form (see BookCrudController, and UiBundle's assets/js/field-focus.js)
    private const array FIELDS = [
        BookPresse::class => 'presses',
        BookMarketing::class => 'marketings',
    ];

    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
    ) {
    }

    public function getPdfDocuments(): array
    {
        $documents = [];
        foreach ($this->mediaRepository->findPdfs() as $media) {
            $name = (string) $media->getName();

            $documents[] = [
                'filename' => $name,
                // The title the editor typed, the file's own name for a document uploaded without one
                'label' => (string) ($media->getTitle() ?: basename($name)),
                'editUrl' => $this->editUrl($media),
            ];
        }

        return $documents;
    }

    // The book's edit screen, on the very collection the document hangs in - null for anything this bundle knows no screen for, the dashboard then showing the row without a pencil
    private function editUrl(object $media): ?string
    {
        $field = self::FIELDS[$media::class] ?? null;
        if (null === $field || !method_exists($media, 'getBook')) {
            return null;
        }

        $book = $media->getBook();
        if (null === $book || null === $book->getId()) {
            return null;
        }

        try {
            return $this->adminUrlGenerator
                ->unsetAll()
                ->setController(BookCrudController::class)
                ->setAction(Action::EDIT)
                ->setEntityId($book->getId())
                ->set('focusField', $field)
                ->generateUrl()
            ;
        } catch (\Throwable) {
            // Same guard as BookLinkHealthCheckProvider: a run from the command line has no admin context, and a dashboard row without a pencil beats a check that stops
            return null;
        }
    }
}
