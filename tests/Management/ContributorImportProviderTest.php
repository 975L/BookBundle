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
use c975L\BookBundle\Entity\ContributorMedia;
use c975L\BookBundle\Management\ContributorExportProvider;
use c975L\BookBundle\Management\ContributorImportProvider;
use c975L\BookBundle\Management\MediaArchiver;
use c975L\BookBundle\Repository\ContributorRepository;
use c975L\BookBundle\Tests\ArchiveTestTrait;
use c975L\UiBundle\Management\BlockDataExporter;
use c975L\UiBundle\Management\BlockDataImporter;
use c975L\UiBundle\Registry\FormBlockDependencyRegistry;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ContributorImportProviderTest extends TestCase
{
    use ArchiveTestTrait;

    public function testSupportsOnlyItsOwnKind(): void
    {
        $provider = $this->createProvider(sys_get_temp_dir());

        $this->assertTrue($provider->supportsImport(ContributorImportProvider::KIND));
        $this->assertFalse($provider->supportsImport('book_serie'));
    }

    public function testRoundTripRebuildsThePersonAndLaysTheirPortraitBackWhereItWas(): void
    {
        $sourceDir = $this->createProjectDir(['medias/book/contributors/portrait-tim-loval/p.webp' => 'portrait-bytes']);
        $contributor = new Contributor()
            ->setSlug('tim-loval')
            ->setName('Tim Loval')
            ->setSummary('Écrit des histoires de chevaliers')
            ->setWebsite('https://example.org/tim')
            ->setPosition(3)
            ->setCreation(new \DateTime('2026-01-02 10:00:00'))
            ->setModification(new \DateTime('2026-01-03 11:00:00'));
        $contributor->addPortrait(new ContributorMedia()->setName('medias/book/contributors/portrait-tim-loval/p.webp')->setPosition(0)->setUpdatedAt(new \DateTimeImmutable('2026-02-01 09:00:00')));

        $export = new ContributorExportProvider($this->createStub(ContributorRepository::class), new BlockDataExporter($sourceDir), new MediaArchiver($this->createStub(EntityManagerInterface::class), $sourceDir))
            ->serialize([$contributor]);

        $filesDir = $this->extractArchive($export['files']);
        $targetDir = $this->createProjectDir([]);
        $persisted = [];

        $result = $this->createProvider($targetDir, persisted: $persisted)->import($export['items'], $filesDir);

        $this->assertSame(['created' => 1, 'updated' => 0], $result);

        $imported = array_values(array_filter($persisted, static fn (object $e) => $e instanceof Contributor))[0];
        $this->assertSame('tim-loval', $imported->getSlug());
        $this->assertSame('Tim Loval', $imported->getName());
        $this->assertSame('https://example.org/tim', $imported->getWebsite());
        $this->assertSame(3, $imported->getPosition());
        $this->assertCount(1, $imported->getPortraits());
        $this->assertSame('portrait-bytes', file_get_contents($targetDir . '/public/medias/book/contributors/portrait-tim-loval/p.webp'));

        $this->removeDir($sourceDir);
        $this->removeDir($filesDir);
        $this->removeDir($targetDir);
    }

    // A book naming them was imported first, so ContributorResolver already gave them a row: filled in here rather than doubled, which would split their page in two
    public function testAShellCreatedByAnEarlierBookImportIsFilledInRatherThanDoubled(): void
    {
        $shell = new Contributor()->setName('Tim Loval')->setSlug('tim-loval')->setCreation(new \DateTime())->setModification(new \DateTime());
        $persisted = [];

        $result = $this->createProvider(sys_get_temp_dir(), existingByName: $shell, persisted: $persisted)
            ->import([['slug' => 'tim-loval-autre', 'name' => 'Tim Loval', 'summary' => 'Écrit des histoires']]);

        $this->assertSame(['created' => 0, 'updated' => 1], $result);
        $this->assertSame([$shell], $persisted);
        $this->assertSame('Écrit des histoires', $shell->getSummary());
    }

    // The name matches, but the row found is somebody the site has written up: an archive naming another Tim Loval is another person, and overwriting them would hand every book crediting ours the stranger's biography
    public function testANamesakeTheSiteHasWrittenUpIsLeftAloneRatherThanOverwritten(): void
    {
        $local = new Contributor()
            ->setName('Tim Loval')
            ->setSlug('tim-loval')
            ->setSummary('Écrit des histoires de chevaliers')
            ->setCreation(new \DateTime())
            ->setModification(new \DateTime());
        $persisted = [];

        $result = $this->createProvider(sys_get_temp_dir(), existingByName: $local, persisted: $persisted)
            ->import([['slug' => 'tim-loval-2', 'name' => 'Tim Loval', 'summary' => 'Photographie des oiseaux']]);

        $this->assertSame(['created' => 1, 'updated' => 0], $result);
        $this->assertSame('Écrit des histoires de chevaliers', $local->getSummary());
        $this->assertNotSame([$local], $persisted);
        $this->assertSame('Photographie des oiseaux', array_values(array_filter($persisted, static fn (object $e) => $e instanceof Contributor))[0]->getSummary());
    }

    // The archive is a faithful copy: someone exported out of the trash comes back to the trash rather than onto the site
    public function testImportKeepsSomeoneInTheTrash(): void
    {
        $persisted = [];
        $this->createProvider(sys_get_temp_dir(), persisted: $persisted)->import([['slug' => 'tim-loval', 'name' => 'Tim Loval', 'isDeleted' => true]]);

        $this->assertTrue(array_values(array_filter($persisted, static fn (object $e) => $e instanceof Contributor))[0]->isDeleted());
    }

    public function testImportDetachesTheMediasTheArchiveNoLongerHolds(): void
    {
        $existing = new Contributor()->setSlug('tim-loval')->setName('Tim Loval')->setCreation(new \DateTime())->setModification(new \DateTime());
        $kept = new ContributorMedia()->setName('medias/book/contributors/portrait-tim-loval/p.webp')->setKind('portrait')->setUpdatedAt(new \DateTimeImmutable());
        $existing->addMedia($kept);
        $existing->addMedia(new ContributorMedia()->setName('medias/book/contributors/background-tim-loval/b.webp')->setKind('background')->setUpdatedAt(new \DateTimeImmutable()));

        $result = $this->createProvider(sys_get_temp_dir(), $existing)->import([[
            'slug' => 'tim-loval',
            'name' => 'Tim Loval',
            'medias' => [['name' => 'medias/book/contributors/portrait-tim-loval/p.webp', 'kind' => 'portrait', 'position' => 0]],
        ]]);

        $this->assertSame(['created' => 0, 'updated' => 1], $result);
        $this->assertSame([$kept], array_values($existing->getMedias()->toArray()));
    }

    /** @param list<object> $persisted filled with everything the import hands to the entity manager, the flush being a stub */
    private function createProvider(string $projectDir, ?Contributor $existing = null, ?Contributor $existingByName = null, array &$persisted = []): ContributorImportProvider
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $repository = $this->createStub(ContributorRepository::class);
        $repository->method('findOneBy')->willReturn($existing);
        $repository->method('findOneByName')->willReturn($existingByName);

        return new ContributorImportProvider(
            $em,
            $repository,
            new BlockDataImporter($em, $this->createStub(FormBlockDependencyRegistry::class)),
            new MediaArchiver($em, $projectDir),
        );
    }
}
