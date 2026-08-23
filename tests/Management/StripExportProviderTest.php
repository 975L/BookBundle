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
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Entity\StripMedia;
use c975L\BookBundle\Management\MediaArchiver;
use c975L\BookBundle\Management\StripExportProvider;
use c975L\BookBundle\Management\StripImportProvider;
use c975L\BookBundle\Repository\StripRepository;
use c975L\BookBundle\Tests\ArchiveTestTrait;
use c975L\UiBundle\Management\BlockDataExporter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class StripExportProviderTest extends TestCase
{
    use ArchiveTestTrait;

    public function testGetKindMatchesStripImportProvider(): void
    {
        $this->assertSame(StripImportProvider::KIND, $this->createProvider(sys_get_temp_dir())->getKind());
    }

    public function testExportAllAsksForEveryStripIncludingTheTrash(): void
    {
        $stripRepository = $this->createMock(StripRepository::class);
        $stripRepository->expects($this->once())->method('findBy')->with([])->willReturn([$this->createStrip()]);

        $item = $this->createProvider(sys_get_temp_dir(), $stripRepository)->exportAll()['items'][0];

        $this->assertSame('la-tuile', $item['slug']);
        $this->assertSame('Le Seigneur, Kalaan', $item['characters']);
        // Derived from the characters on the way back in, so it is not carried
        $this->assertArrayNotHasKey('charactersSlug', $item);
    }

    // What the index's "Export selection" batch action hands to ContentExporter (see TrashableCrudTrait::exportSelection())
    public function testSerializeIdsExportsOnlyTheCheckedRows(): void
    {
        $stripRepository = $this->createMock(StripRepository::class);
        $stripRepository->expects($this->once())->method('findBy')->with(['id' => [11]])->willReturn([$this->createStrip()]);

        $data = $this->createProvider(sys_get_temp_dir(), $stripRepository)->serializeIds([11]);

        $this->assertSame(['la-tuile'], array_column($data['items'], 'slug'));
    }

    public function testSerializeNamesTheSerieBySlugAndRegistersThePlates(): void
    {
        $projectDir = $this->createProjectDir(['medias/book/strips/plate-la-tuile/p.webp' => 'plate-bytes']);

        $strip = $this->createStrip()->setSerie(new Serie()->setSlug('la-guilde')->setTitle('La Guilde des Seigneurs'));
        $strip->addMedia(new StripMedia()->setName('medias/book/strips/plate-la-tuile/p.webp')->setKind('plate')->setPosition(0)->setUpdatedAt(new \DateTimeImmutable()));

        $item = $this->createProvider($projectDir)->serialize([$strip])['items'][0];

        $this->assertSame('la-guilde', $item['serie']);
        $this->assertSame('La Guilde des Seigneurs', $item['serieTitle']);
        $this->assertSame('medias/book/strips/plate-la-tuile/p.webp', $item['medias'][0]['name']);

        $this->removeDir($projectDir);
    }

    private function createStrip(): Strip
    {
        return new Strip()
            ->setSlug('la-tuile')
            ->setTitle('La tuile')
            ->setCharacters('Le Seigneur, Kalaan')
            ->setCreation(new \DateTime('2026-01-02 10:00:00'))
            ->setModification(new \DateTime('2026-01-03 11:00:00'));
    }

    private function createProvider(string $projectDir, ?StripRepository $stripRepository = null): StripExportProvider
    {
        return new StripExportProvider(
            $stripRepository ?? $this->createStub(StripRepository::class),
            new BlockDataExporter($projectDir),
            new MediaArchiver($this->createStub(EntityManagerInterface::class), $projectDir),
        );
    }
}
