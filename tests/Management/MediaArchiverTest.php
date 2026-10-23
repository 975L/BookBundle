<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Management;

use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Entity\SerieMedia;
use c975L\BookBundle\Management\MediaArchiver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

// The derivatives an upload cut beside a book's page only an upload can make again, so an archive carries them along with the stored file
class MediaArchiverTest extends TestCase
{
    private const string PAGE = 'medias/book/books/page-la-moto/page-abc123.webp';

    private string $projectDir = '';

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/media-archiver-' . uniqid();
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    public function testExportCarriesTheDerivativesBesideTheFile(): void
    {
        $this->write(self::PAGE);
        $this->write('medias/book/books/page-la-moto/page-abc123-highres.webp');

        $files = [];
        $entry = $this->archiver()->export(new BookMedia()->setKind('page')->setName(self::PAGE), $files)['file'];

        $this->assertSame([$entry, substr($entry, 0, -5) . '-highres.webp'], array_keys($files));
    }

    // A media cutting no derivatives has none to carry, whatever sits beside its file
    public function testExportCarriesNothingMoreForAMediaCuttingNoDerivatives(): void
    {
        $this->write('medias/book/series/cover-abc123.webp');
        $this->write('medias/book/series/cover-abc123-highres.webp');

        $files = [];
        $this->archiver()->export(new SerieMedia()->setName('medias/book/series/cover-abc123.webp'), $files);

        $this->assertCount(1, $files);
    }

    public function testRestoreFilesLaysTheDerivativesBackBesideTheFile(): void
    {
        $filesDir = $this->projectDir . '/archive';
        new Filesystem()->dumpFile($filesDir . '/files/f00_page-abc123.webp', 'x');
        new Filesystem()->dumpFile($filesDir . '/files/f00_page-abc123-highres.webp', 'x');

        $media = new BookMedia()->setKind('page')->setName(self::PAGE);
        $this->archiver()->restoreFiles([[$media, ['file' => 'files/f00_page-abc123.webp']]], $filesDir);

        $this->assertFileExists($this->projectDir . '/public/' . self::PAGE);
        $this->assertFileExists($this->projectDir . '/public/medias/book/books/page-la-moto/page-abc123-highres.webp');
        $this->assertFileDoesNotExist($this->projectDir . '/public/medias/book/books/page-la-moto/page-abc123-thumb.webp');
    }

    private function archiver(): MediaArchiver
    {
        return new MediaArchiver($this->createStub(EntityManagerInterface::class), $this->projectDir);
    }

    private function write(string $name): void
    {
        new Filesystem()->dumpFile($this->projectDir . '/public/' . $name, 'x');
    }
}
