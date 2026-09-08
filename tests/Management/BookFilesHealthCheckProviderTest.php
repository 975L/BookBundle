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
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Entity\Media;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\SerieMedia;
use c975L\BookBundle\Management\BookFilesHealthCheckProvider;
use c975L\BookBundle\Repository\MediaRepository;
use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

class BookFilesHealthCheckProviderTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/book-files-health-check-test-' . uniqid();
        new Filesystem()->mkdir($this->projectDir . '/public');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    /**
     * @param array<int, array{0: Media, 1: bool}> $rows the media and whether its file sits on disk
     */
    private function createProvider(array $rows): BookFilesHealthCheckProvider
    {
        $medias = [];
        foreach ($rows as [$media, $onDisk]) {
            if ($onDisk) {
                $path = $this->projectDir . '/public/' . $media->getName();
                new Filesystem()->mkdir(\dirname($path));
                file_put_contents($path, 'file');
            }

            $medias[] = $media;
        }

        $mediaRepository = $this->createStub(MediaRepository::class);
        $mediaRepository->method('findWithFilename')->willReturn($medias);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('https://example.com');

        $adminUrlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('unsetAll')->willReturnSelf();
        $adminUrlGenerator->method('setController')->willReturnSelf();
        $adminUrlGenerator->method('setAction')->willReturnSelf();
        $adminUrlGenerator->method('setEntityId')->willReturnSelf();
        $adminUrlGenerator->method('generateUrl')->willReturn('/management/edit');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $params = []) => $id . '|' . implode('', $params)
        );

        return new BookFilesHealthCheckProvider(
            $mediaRepository,
            $adminUrlGenerator,
            $configService,
            $translator,
            $this->projectDir,
        );
    }

    private function createBookMedia(string $filename): BookMedia
    {
        $book = new Book()->setTitle('Les Triados');
        new \ReflectionProperty(Book::class, 'id')->setValue($book, 1);

        return new BookMedia()->setName($filename)->setBook($book);
    }

    private function createSerieMedia(string $filename): SerieMedia
    {
        $serie = new Serie()->setTitle('La Guilde des Seigneurs');
        new \ReflectionProperty(Serie::class, 'id')->setValue($serie, 2);

        return new SerieMedia()->setName($filename)->setSerie($serie);
    }

    public function testGetKind(): void
    {
        $this->assertSame('files-book', $this->createProvider([])->getKind());
    }

    public function testACatalogDeclaringNoFileReportsNothing(): void
    {
        $this->assertSame([], $this->createProvider([])->runChecks());
    }

    public function testADeclaredFileMissingFromTheServerIsAnError(): void
    {
        $rows = $this->createProvider([[$this->createBookMedia('medias/books/triados.webp'), false]])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $rows[0]['status']);
        $this->assertSame('https://example.com/medias/books/triados.webp', $rows[0]['url']);
        $this->assertSame('Les Triados', $rows[0]['label']);
        $this->assertSame('/management/edit', $rows[0]['editUrl']);
    }

    public function testAFileInPlaceStillGetsItsRow(): void
    {
        $rows = $this->createProvider([[$this->createBookMedia('medias/books/triados.webp'), true]])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame(HealthCheckResult::STATUS_OK, $rows[0]['status']);
    }

    // Four owners share the one table: a serie's picture is named after its serie, not after a book it has no link to
    public function testAMediaIsNamedAfterItsOwnOwner(): void
    {
        $rows = $this->createProvider([[$this->createSerieMedia('medias/series/guilde.webp'), true]])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame('La Guilde des Seigneurs', $rows[0]['label']);
    }
}
