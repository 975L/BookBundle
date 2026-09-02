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
use c975L\BookBundle\Management\SerieResolver;
use c975L\BookBundle\Management\StripExportProvider;
use c975L\BookBundle\Management\StripImportProvider;
use c975L\BookBundle\Repository\SerieRepository;
use c975L\BookBundle\Repository\StripRepository;
use c975L\BookBundle\Tests\ArchiveTestTrait;
use c975L\UiBundle\Management\BlockDataExporter;
use c975L\UiBundle\Management\BlockDataImporter;
use c975L\UiBundle\Registry\FormBlockDependencyRegistry;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class StripImportProviderTest extends TestCase
{
    use ArchiveTestTrait;

    public function testSupportsOnlyItsOwnKind(): void
    {
        $provider = $this->createProvider(sys_get_temp_dir());

        $this->assertTrue($provider->supportsImport(StripImportProvider::KIND));
        $this->assertFalse($provider->supportsImport('book_serie'));
    }

    public function testRoundTripRebuildsTheStripItsCharactersAndItsPlates(): void
    {
        $sourceDir = $this->createProjectDir(['medias/book/strips/plate-la-tuile/p.webp' => 'plate-bytes']);
        $strip = new Strip()
            ->setSlug('la-tuile')
            ->setTitle('La tuile')
            ->setNumber(12)
            ->setCharacters('Le Seigneur, Alwin')
            ->setSummary('Une planche')
            ->setPublished(new \DateTime('2026-03-04'))
            ->setCreation(new \DateTime('2026-01-02 10:00:00'))
            ->setModification(new \DateTime('2026-01-03 11:00:00'))
            ->setSerie(new Serie()->setSlug('la-compagnie')->setTitle('La Compagnie des Ombres'));
        $strip->addMedia(new StripMedia()->setName('medias/book/strips/plate-la-tuile/p.webp')->setKind('plate')->setPosition(0)->setUpdatedAt(new \DateTimeImmutable('2026-02-01 09:00:00')));

        $export = new StripExportProvider($this->createStub(StripRepository::class), new BlockDataExporter($sourceDir), new MediaArchiver($this->createStub(EntityManagerInterface::class), $sourceDir))
            ->serialize([$strip]);

        $filesDir = $this->extractArchive($export['files']);
        $targetDir = $this->createProjectDir([]);
        $persisted = [];

        $result = $this->createProvider($targetDir, persisted: $persisted)->import($export['items'], $filesDir);

        $this->assertSame(['created' => 1, 'updated' => 0], $result);

        $imported = array_values(array_filter($persisted, static fn (object $e) => $e instanceof Strip))[0];
        $this->assertSame(12, $imported->getNumber());
        $this->assertSame('Le Seigneur, Alwin', $imported->getCharacters());
        // Derived from the characters rather than carried, so it comes back on its own
        $this->assertSame('le-seigneur,alwin', $imported->getCharactersSlug());
        $this->assertSame('2026-03-04', $imported->getPublished()?->format('Y-m-d'));
        // The serie this environment doesn't hold yet, created on the fly rather than dropped
        $this->assertSame('la-compagnie', $imported->getSerie()?->getSlug());
        $this->assertSame('plate-bytes', file_get_contents($targetDir . '/public/medias/book/strips/plate-la-tuile/p.webp'));

        $this->removeDir($sourceDir);
        $this->removeDir($filesDir);
        $this->removeDir($targetDir);
    }

    // Two strips of the same absent serie get the one shell, findOneBy() not seeing a serie persisted but not yet flushed
    public function testImportCreatesTheAbsentSerieOnlyOnceForAWholeArchive(): void
    {
        $persisted = [];
        $this->createProvider(sys_get_temp_dir(), persisted: $persisted)->import([
            ['slug' => 'la-tuile', 'title' => 'La tuile', 'serie' => 'la-compagnie', 'serieTitle' => 'La Compagnie des Ombres'],
            ['slug' => 'le-mur', 'title' => 'Le mur', 'serie' => 'la-compagnie', 'serieTitle' => 'La Compagnie des Ombres'],
        ]);

        $series = array_values(array_filter($persisted, static fn (object $e) => $e instanceof Serie));
        $this->assertCount(1, $series);
        $this->assertSame('La Compagnie des Ombres', $series[0]->getTitle());
    }

    // @param list<object> $persisted filled with everything the import hands to the entity manager, the flush being a stub
    private function createProvider(string $projectDir, ?Strip $existingStrip = null, array &$persisted = []): StripImportProvider
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $stripRepository = $this->createStub(StripRepository::class);
        $stripRepository->method('findOneBy')->willReturn($existingStrip);

        return new StripImportProvider(
            $em,
            $stripRepository,
            new BlockDataImporter($em, $this->createStub(FormBlockDependencyRegistry::class)),
            new MediaArchiver($em, $projectDir),
            new SerieResolver($em, $this->createStub(SerieRepository::class)),
        );
    }
}
