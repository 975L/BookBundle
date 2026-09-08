<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Management;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookMarketing;
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Entity\BookPresse;
use c975L\BookBundle\Entity\Media;
use c975L\BookBundle\Management\BookPdfDocumentSource;
use c975L\BookBundle\Repository\MediaRepository;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;

// What this bundle hands to UiBundle's thumbnail check, which reads its own library and would otherwise see none of these documents - see BookPdfDocumentSource
class BookPdfDocumentSourceTest extends TestCase
{
    // The row as the check will read it: the path the file is served under, the title the editor typed, and the screen the pencil opens
    public function testAPressDocumentIsDeclaredWithItsPathTitleAndEditScreen(): void
    {
        $media = $this->presse('medias/book/presse/contes-du-soir/dossier-de-presse-68d1.pdf', 'Dossier de presse');

        $documents = $this->source([$media])->getPdfDocuments();

        $this->assertCount(1, $documents);
        $this->assertSame('medias/book/presse/contes-du-soir/dossier-de-presse-68d1.pdf', $documents[0]['filename']);
        $this->assertSame('Dossier de presse', $documents[0]['label']);
        $this->assertSame('BookCrudController/4/presses', $documents[0]['editUrl']);
    }

    // A document uploaded without a title still has to name itself on the dashboard, and its own file name is the only thing left to name it by
    public function testADocumentWithNoTitleFallsBackOnItsFileName(): void
    {
        $documents = $this->source([$this->presse('medias/book/presse/contes-du-soir/dossier-68d1.pdf', null)])->getPdfDocuments();

        $this->assertSame('dossier-68d1.pdf', $documents[0]['label']);
    }

    // The two collections are edited on the same screen but not on the same tab, and the pencil has to land on the right one
    public function testAMarketingDocumentOpensItsOwnTab(): void
    {
        $documents = $this->source([$this->marketing('medias/book/marketing/contes-du-soir/argumentaire-68d1.pdf')])->getPdfDocuments();

        $this->assertSame('BookCrudController/4/marketings', $documents[0]['editUrl']);
    }

    // A PDF hanging in a collection this bundle opens no screen on is still declared - the check reports it, the dashboard just shows the row without a pencil
    public function testADocumentThisBundleOpensNoScreenOnIsDeclaredWithoutAnEditUrl(): void
    {
        $media = new BookMedia()->setName('medias/book/contes-du-soir/extrait-68d1.pdf');
        $media->setBook($this->book());

        $documents = $this->source([$media])->getPdfDocuments();

        $this->assertCount(1, $documents);
        $this->assertNull($documents[0]['editUrl']);
    }

    // A document attached to a book that was never saved has no screen to open on either
    public function testADocumentWhoseBookHasNoIdCarriesNoEditUrl(): void
    {
        $media = new BookPresse()->setName('medias/book/presse/temp/dossier-68d1.pdf');
        $media->setBook(new Book()->setTitle('Contes du Soir')->setSlug('contes-du-soir'));

        $this->assertNull($this->source([$media])->getPdfDocuments()[0]['editUrl']);
    }

    // A catalog holding no PDF declares nothing, the check then reporting on UiBundle's own library alone
    public function testACatalogWithNoDocumentDeclaresNothing(): void
    {
        $this->assertSame([], $this->source([])->getPdfDocuments());
    }

    /** @param list<Media> $medias */
    private function source(array $medias): BookPdfDocumentSource
    {
        $repository = $this->createStub(MediaRepository::class);
        $repository->method('findPdfs')->willReturn($medias);

        return new BookPdfDocumentSource($repository, $this->adminUrlGenerator());
    }

    private function presse(string $name, ?string $title): BookPresse
    {
        $media = new BookPresse()->setName($name)->setTitle($title);
        $media->setBook($this->book());

        return $media;
    }

    private function marketing(string $name): BookMarketing
    {
        $media = new BookMarketing()->setName($name)->setTitle('Argumentaire');
        $media->setBook($this->book());

        return $media;
    }

    private function book(): Book
    {
        $book = new Book()->setTitle('Contes du Soir')->setSlug('contes-du-soir');
        new \ReflectionProperty(Book::class, 'id')->setValue($book, 4);

        return $book;
    }

    // Rebuilds the screen, the book and the tab the edit URL opens on, as BookLinkHealthCheckProviderTest does for the platforms
    private function adminUrlGenerator(): AdminUrlGeneratorInterface
    {
        $controller = null;
        $entityId = null;
        $focusField = null;

        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setAction')->willReturnSelf();
        $generator->method('setController')->willReturnCallback(function (string $fqcn) use (&$controller, $generator) {
            $controller = substr((string) strrchr($fqcn, '\\'), 1);

            return $generator;
        });
        $generator->method('setEntityId')->willReturnCallback(function ($id) use (&$entityId, $generator) {
            $entityId = $id;

            return $generator;
        });
        $generator->method('set')->willReturnCallback(function (string $name, $value) use (&$focusField, $generator) {
            $focusField = 'focusField' === $name ? $value : $focusField;

            return $generator;
        });
        $generator->method('generateUrl')->willReturnCallback(static function () use (&$controller, &$entityId, &$focusField): string {
            return sprintf('%s/%s/%s', $controller, $entityId, $focusField);
        });

        return $generator;
    }
}
