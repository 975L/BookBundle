<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Management;

use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\SerieMedia;
use c975L\BookBundle\Management\ContributorResolver;
use c975L\BookBundle\Management\MediaArchiver;
use c975L\BookBundle\Management\SerieExportProvider;
use c975L\BookBundle\Management\SerieImportProvider;
use c975L\BookBundle\Repository\ContributorRepository;
use c975L\BookBundle\Repository\SerieRepository;
use c975L\BookBundle\Tests\ArchiveTestTrait;
use c975L\UiBundle\Management\BlockDataExporter;
use c975L\UiBundle\Management\BlockDataImporter;
use c975L\UiBundle\Registry\FormBlockDependencyRegistry;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

class SerieImportProviderTest extends TestCase
{
    use ArchiveTestTrait;

    public function testSupportsOnlyItsOwnKind(): void
    {
        $provider = $this->createProvider(sys_get_temp_dir());

        $this->assertTrue($provider->supportsImport(SerieImportProvider::KIND));
        $this->assertFalse($provider->supportsImport('book_book'));
    }

    public function testRoundTripRebuildsTheSerieAndLaysItsFilesBackWhereTheyWere(): void
    {
        $sourceDir = $this->createProjectDir(['medias/book/series/cover-la-compagnie/c.webp' => 'cover-bytes']);
        $serie = new Serie()
            ->setSlug('la-compagnie')
            ->setTitle('La Compagnie des Ombres')
            ->setSummary('Un univers')
            ->setKind('bd')
            ->setLanguage('fr')
            ->setAuthor(new Contributor()->setName('Laurent Marquet')->setSlug('laurent-marquet'))
            ->setCreation(new \DateTime('2026-01-02 10:00:00'))
            ->setModification(new \DateTime('2026-01-03 11:00:00'));
        $serie->addCover(new SerieMedia()->setName('medias/book/series/cover-la-compagnie/c.webp')->setPosition(0)->setUpdatedAt(new \DateTimeImmutable('2026-02-01 09:00:00')));

        $export = new SerieExportProvider($this->createStub(SerieRepository::class), new BlockDataExporter($sourceDir), new MediaArchiver($this->createStub(EntityManagerInterface::class), $sourceDir))
            ->serialize([$serie]);

        $filesDir = $this->extractArchive($export['files']);
        $targetDir = $this->createProjectDir([]);
        $persisted = [];

        $result = $this->createProvider($targetDir, persisted: $persisted)->import($export['items'], $filesDir);

        $this->assertSame(['created' => 1, 'updated' => 0], $result);

        $imported = array_values(array_filter($persisted, static fn (object $e) => $e instanceof Serie))[0];
        $this->assertSame('la-compagnie', $imported->getSlug());
        $this->assertSame('bd', $imported->getKind());
        $this->assertSame('Laurent Marquet', $imported->getAuthor()?->getName());
        $this->assertCount(1, $imported->getCovers());
        $this->assertSame('cover-bytes', file_get_contents($targetDir . '/public/medias/book/series/cover-la-compagnie/c.webp'));

        $this->removeDir($sourceDir);
        $this->removeDir($filesDir);
        $this->removeDir($targetDir);
    }

    // The archive is a faithful copy: a serie exported out of the trash comes back to the trash rather than onto the site
    public function testImportKeepsASerieInTheTrash(): void
    {
        $persisted = [];
        $this->createProvider(sys_get_temp_dir(), persisted: $persisted)->import([['slug' => 'la-compagnie', 'title' => 'La Compagnie', 'isDeleted' => true]]);

        $this->assertTrue(array_values(array_filter($persisted, static fn (object $e) => $e instanceof Serie))[0]->isDeleted());
    }

    public function testImportDetachesTheMediasTheArchiveNoLongerHolds(): void
    {
        $existing = new Serie()->setSlug('la-compagnie')->setTitle('La Compagnie')->setCreation(new \DateTime())->setModification(new \DateTime());
        $kept = new SerieMedia()->setName('medias/book/series/cover-la-compagnie/c.webp')->setKind('cover')->setUpdatedAt(new \DateTimeImmutable());
        $existing->addMedia($kept);
        $existing->addMedia(new SerieMedia()->setName('medias/book/series/logo-la-compagnie/l.webp')->setKind('logo')->setUpdatedAt(new \DateTimeImmutable()));

        $result = $this->createProvider(sys_get_temp_dir(), $existing)->import([[
            'slug' => 'la-compagnie',
            'title' => 'La Compagnie',
            'medias' => [['name' => 'medias/book/series/cover-la-compagnie/c.webp', 'kind' => 'cover', 'position' => 0]],
        ]]);

        $this->assertSame(['created' => 0, 'updated' => 1], $result);
        $this->assertSame([$kept], array_values($existing->getMedias()->toArray()));
    }

    /** @param list<object> $persisted filled with everything the import hands to the entity manager, the flush being a stub */
    private function createProvider(string $projectDir, ?Serie $existingSerie = null, array &$persisted = []): SerieImportProvider
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $serieRepository = $this->createStub(SerieRepository::class);
        $serieRepository->method('findOneBy')->willReturn($existingSerie);

        return new SerieImportProvider(
            $em,
            new ContributorResolver($em, $this->createStub(ContributorRepository::class), new AsciiSlugger()),
            $serieRepository,
            new BlockDataImporter($em, $this->createStub(FormBlockDependencyRegistry::class)),
            new MediaArchiver($em, $projectDir),
        );
    }
}
