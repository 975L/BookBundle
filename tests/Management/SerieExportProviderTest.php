<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Management;

use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\SerieMedia;
use c975L\BookBundle\Management\MediaArchiver;
use c975L\BookBundle\Management\SerieExportProvider;
use c975L\BookBundle\Management\SerieImportProvider;
use c975L\BookBundle\Repository\SerieRepository;
use c975L\BookBundle\Tests\ArchiveTestTrait;
use c975L\UiBundle\Management\BlockDataExporter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class SerieExportProviderTest extends TestCase
{
    use ArchiveTestTrait;

    public function testGetKindMatchesSerieImportProvider(): void
    {
        $this->assertSame(SerieImportProvider::KIND, $this->createProvider(sys_get_temp_dir())->getKind());
    }

    public function testExportAllAsksForEverySerieIncludingTheTrash(): void
    {
        $serieRepository = $this->createMock(SerieRepository::class);
        // findBy([]) rather than findAll(), which hides the trash
        $serieRepository->expects($this->once())->method('findBy')->with([])->willReturn([$this->createSerie()]);

        $data = $this->createProvider(sys_get_temp_dir(), $serieRepository)->exportAll();

        $this->assertSame('papa-calin', $data['items'][0]['slug']);
        $this->assertSame('Papa Câlin', $data['items'][0]['title']);
        $this->assertFalse($data['items'][0]['isDeleted']);
    }

    // What the index's "Export selection" batch action hands to ContentExporter (see TrashableCrudTrait::exportSelection())
    public function testSerializeIdsExportsOnlyTheCheckedRows(): void
    {
        $serieRepository = $this->createMock(SerieRepository::class);
        $serieRepository->expects($this->once())->method('findBy')->with(['id' => [2]])->willReturn([$this->createSerie()]);

        $data = $this->createProvider(sys_get_temp_dir(), $serieRepository)->serializeIds([2]);

        $this->assertSame(['papa-calin'], array_column($data['items'], 'slug'));
    }

    public function testSerializeRegistersTheCoversAndTheLogosOfASerie(): void
    {
        $projectDir = $this->createProjectDir([
            'medias/book/series/cover-papa-calin/c.webp' => 'cover-bytes',
            'medias/book/series/logo-papa-calin/l.webp' => 'logo-bytes',
        ]);

        $serie = $this->createSerie();
        $serie->addCover(new SerieMedia()->setName('medias/book/series/cover-papa-calin/c.webp')->setPosition(0)->setUpdatedAt(new \DateTimeImmutable()));
        $serie->addLogo(new SerieMedia()->setName('medias/book/series/logo-papa-calin/l.webp')->setPosition(1)->setUpdatedAt(new \DateTimeImmutable()));

        $data = $this->createProvider($projectDir)->serialize([$serie]);

        $this->assertSame(['cover', 'logo'], array_column($data['items'][0]['medias'], 'kind'));
        $this->assertCount(2, $data['files']);

        $this->removeDir($projectDir);
    }

    private function createSerie(): Serie
    {
        return new Serie()
            ->setSlug('papa-calin')
            ->setTitle('Papa Câlin')
            ->setCreation(new \DateTime('2026-01-02 10:00:00'))
            ->setModification(new \DateTime('2026-01-03 11:00:00'));
    }

    private function createProvider(string $projectDir, ?SerieRepository $serieRepository = null): SerieExportProvider
    {
        return new SerieExportProvider(
            $serieRepository ?? $this->createStub(SerieRepository::class),
            new BlockDataExporter($projectDir),
            new MediaArchiver($this->createStub(EntityManagerInterface::class), $projectDir),
        );
    }
}
