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
use c975L\BookBundle\Entity\ContributorLink;
use c975L\BookBundle\Entity\ContributorMedia;
use c975L\BookBundle\Management\ContributorExportProvider;
use c975L\BookBundle\Management\ContributorImportProvider;
use c975L\BookBundle\Management\MediaArchiver;
use c975L\BookBundle\Repository\ContributorRepository;
use c975L\BookBundle\Tests\ArchiveTestTrait;
use c975L\UiBundle\Management\BlockDataExporter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ContributorExportProviderTest extends TestCase
{
    use ArchiveTestTrait;

    public function testGetKindMatchesContributorImportProvider(): void
    {
        $this->assertSame(ContributorImportProvider::KIND, $this->createProvider(sys_get_temp_dir())->getKind());
    }

    // findBy([]) and not findAll(), which hides the trash: an archive is a faithful copy
    public function testExportAllAsksForEveryPersonIncludingTheTrash(): void
    {
        $contributorRepository = $this->createMock(ContributorRepository::class);
        $contributorRepository->expects($this->once())->method('findBy')->with([])->willReturn([$this->createContributor()]);

        $item = $this->createProvider(sys_get_temp_dir(), $contributorRepository)->exportAll()['items'][0];

        $this->assertSame('camille-ferrand', $item['slug']);
        $this->assertSame('Camille Ferrand', $item['name']);
        $this->assertSame('https://camille-ferrand.example', $item['website']);
    }

    // What the index's "Export selection" batch action hands to ContentExporter (see TrashableCrudTrait::exportSelection())
    public function testSerializeIdsExportsOnlyTheCheckedRows(): void
    {
        $contributorRepository = $this->createMock(ContributorRepository::class);
        $contributorRepository->expects($this->once())->method('findBy')->with(['id' => [7]])->willReturn([$this->createContributor()]);

        $data = $this->createProvider(sys_get_temp_dir(), $contributorRepository)->serializeIds([7]);

        $this->assertSame(['camille-ferrand'], array_column($data['items'], 'slug'));
    }

    // Where a row stands travels with it, so a round-trip puts nobody back on the site an admin had taken off it
    public function testSerializeCarriesWhereThePersonStands(): void
    {
        $contributor = $this->createContributor()->setHidden(true);
        $contributor->setIsDeleted(true);

        $item = $this->createProvider(sys_get_temp_dir())->serialize([$contributor])['items'][0];

        $this->assertTrue($item['hidden']);
        $this->assertTrue($item['isDeleted']);
        $this->assertSame(3, $item['position']);
    }

    // What they signed is not carried here: each book and each serie names them by name, under its own kind
    public function testSerializeRegistersTheirFilesAndNotWhatTheySigned(): void
    {
        $projectDir = $this->createProjectDir(['medias/book/contributors/camille-ferrand/portrait.webp' => 'portrait-bytes']);

        $contributor = $this->createContributor();
        $contributor->addMedia(new ContributorMedia()->setName('medias/book/contributors/camille-ferrand/portrait.webp')->setKind('portrait')->setPosition(0)->setUpdatedAt(new \DateTimeImmutable()));

        $item = $this->createProvider($projectDir)->serialize([$contributor])['items'][0];

        $this->assertSame('medias/book/contributors/camille-ferrand/portrait.webp', $item['medias'][0]['name']);
        $this->assertArrayNotHasKey('books', $item);
        $this->assertArrayNotHasKey('series', $item);

        $this->removeDir($projectDir);
    }

    // Where their books are bought is theirs and not a book's, so it travels with the person
    public function testSerializeCarriesThePlatformsTheirBooksAreBoughtAt(): void
    {
        $contributor = $this->createContributor();
        $contributor->addLink(new ContributorLink()->setKind('epub_fnac')->setUrl('https://www.fnac.com/ia1/Camille-Ferrand')->setPosition(10));

        $item = $this->createProvider(sys_get_temp_dir())->serialize([$contributor])['items'][0];

        $this->assertSame([['kind' => 'epub_fnac', 'url' => 'https://www.fnac.com/ia1/Camille-Ferrand', 'position' => 10]], $item['links']);
    }

    private function createContributor(): Contributor
    {
        return new Contributor()
            ->setSlug('camille-ferrand')
            ->setName('Camille Ferrand')
            ->setSummary('Elle écrit des histoires du soir.')
            ->setWebsite('https://camille-ferrand.example')
            ->setPosition(3)
            ->setCreation(new \DateTime('2026-01-02 10:00:00'))
            ->setModification(new \DateTime('2026-01-03 11:00:00'));
    }

    private function createProvider(string $projectDir, ?ContributorRepository $contributorRepository = null): ContributorExportProvider
    {
        return new ContributorExportProvider(
            $contributorRepository ?? $this->createStub(ContributorRepository::class),
            new BlockDataExporter($projectDir),
            new MediaArchiver($this->createStub(EntityManagerInterface::class), $projectDir),
        );
    }
}
