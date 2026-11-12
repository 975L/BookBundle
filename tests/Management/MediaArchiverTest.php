<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Management;

use c975L\BookBundle\Entity\BookColoring;
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Entity\BookPrintable;
use c975L\BookBundle\Entity\BookVideo;
use c975L\BookBundle\Entity\SerieMedia;
use c975L\BookBundle\Management\MediaArchiver;
use Doctrine\Common\Collections\ArrayCollection;
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

    // A document to print travels with its day both ways, else an import would put out at once what was planned week by week
    public function testADocumentToPrintKeepsItsDayThroughAnArchive(): void
    {
        $name = 'medias/book/printables/coloriage-abc123.pdf';
        $this->write($name);

        $files = [];
        $data = $this->archiver()->export(new BookPrintable()->setName($name)->setPublishedOn(new \DateTimeImmutable('2027-01-13')), $files);
        $this->assertSame('2027-01-13', $data['publishedOn']);

        [[$imported]] = $this->archiver()->sync(new ArrayCollection(), [$data], static fn (): BookPrintable => new BookPrintable(), static fn (): null => null, static fn (): null => null);
        $this->assertSame('2027-01-13', $imported->getPublishedOn()?->format('Y-m-d'));
    }

    // A drawing to colour travels with its day and its crayons, the illustration they were read off being kept nowhere
    public function testADrawingToColourTravelsWithItsDayAndItsCrayons(): void
    {
        $name = 'medias/book/colorings/item-1-abc123.png';
        $this->write($name);

        $files = [];
        $data = $this->archiver()->export(new BookColoring()->setName($name)->setPublishedOn(new \DateTimeImmutable('2027-01-13'))->setPalette(['#ff0000', '#1a1a1a']), $files);
        $this->assertSame(['#ff0000', '#1a1a1a'], $data['palette']);

        [[$imported]] = $this->archiver()->sync(new ArrayCollection(), [$data], static fn (): BookColoring => new BookColoring(), static fn (): null => null, static fn (): null => null);
        $this->assertSame('2027-01-13', $imported->getPublishedOn()?->format('Y-m-d'));
        $this->assertSame(['#ff0000', '#1a1a1a'], $imported->getPalette());
    }

    // Any other media carries no such key
    public function testAnotherMediaCarriesNoDay(): void
    {
        $this->write(self::PAGE);

        $files = [];
        $this->assertArrayNotHasKey('publishedOn', $this->archiver()->export(new BookMedia()->setName(self::PAGE), $files));
    }

    // A manifest naming a file outside the archive's own folder copies nothing, its derivatives neither
    public function testRestoreFilesReadsNothingOutsideTheArchive(): void
    {
        $filesDir = $this->projectDir . '/archive';
        new Filesystem()->dumpFile($filesDir . '/files/kept.webp', 'x');
        new Filesystem()->dumpFile($this->projectDir . '/.env', 'APP_SECRET=item-1');

        $media = new BookMedia()->setKind('page')->setName(self::PAGE);
        $this->archiver()->restoreFiles([[$media, ['file' => '../.env']]], $filesDir);

        $this->assertFileDoesNotExist($this->projectDir . '/public/' . self::PAGE);
    }

    // A name this bundle would never write - a script, a page served from the site's own origin, a way out of its folder - is not stored at all, so no export carries it on
    public function testSyncLeavesOutAnEntryNamedOutsideWhatAnUploadWrites(): void
    {
        $entries = [
            ['name' => 'medias/book/books/item-1.php'],
            ['name' => 'medias/book/books/item-1.html'],
            ['name' => 'medias/book/books/item-1.avif'],
            ['name' => 'medias/book/../../.env.webp'],
            ['name' => 'medias/book/books/item-1.webp'],
        ];

        $written = $this->archiver()->sync(new ArrayCollection(), $entries, static fn (): BookMedia => new BookMedia(), static fn (): null => null, static fn (): null => null);

        $this->assertSame(['medias/book/books/item-1.webp'], array_map(static fn (array $row): ?string => $row[0]->getName(), $written));
    }

    // A row the site holds under such a name - a planche imported from a former site's own folder - is neither written over nor detached by a round trip
    public function testSyncLeavesAnExistingRowUnderSuchANameAlone(): void
    {
        $existing = new BookMedia()->setName('medias/tribu/012/item-1.jpg')->setTitle('item-1');
        $detached = [];

        $written = $this->archiver()->sync(
            new ArrayCollection([$existing]),
            [['name' => 'medias/tribu/012/item-1.jpg', 'title' => 'item-2']],
            static fn (): BookMedia => new BookMedia(),
            static fn (): null => null,
            static function (BookMedia $media) use (&$detached): void {
                $detached[] = $media;
            },
        );

        $this->assertSame([], $written);
        $this->assertSame([], $detached);
        $this->assertSame('item-1', $existing->getTitle());
    }

    // Two hosted videos carry no name: each archive entry gets its own row, and the former rows are detached rather than one of them written over
    public function testNamelessEntriesEachGetTheirOwnRow(): void
    {
        $former = [new BookVideo()->setYoutubeUrl('https://youtu.be/item-1'), new BookVideo()->setYoutubeUrl('https://youtu.be/item-2')];
        $detached = [];

        $written = $this->archiver()->sync(
            new ArrayCollection($former),
            [['youtubeUrl' => 'https://youtu.be/item-3'], ['youtubeUrl' => 'https://youtu.be/item-4']],
            static fn (): BookVideo => new BookVideo(),
            static fn (): null => null,
            static function (BookVideo $video) use (&$detached): void {
                $detached[] = $video;
            },
        );

        $this->assertSame(['https://youtu.be/item-3', 'https://youtu.be/item-4'], array_map(static fn (array $row): ?string => $row[0]->getYoutubeUrl(), $written));
        $this->assertSame($former, $detached);
    }

    // The copy goes round Vich: the drawing's fingerprint is written again off the bytes laid back, so the app tells drawings apart and a rerun of the import uploads nothing twice
    public function testRestoreFilesWritesTheFingerprintOfADrawingToColour(): void
    {
        $filesDir = $this->projectDir . '/archive';
        new Filesystem()->dumpFile($filesDir . '/files/f00_item-1.png', 'png-bytes');

        $coloring = new BookColoring()->setName('medias/book/colorings/item-1-abc123.png');
        $this->archiver()->restoreFiles([[$coloring, ['file' => 'files/f00_item-1.png']]], $filesDir);

        $this->assertSame(hash('xxh128', 'png-bytes'), $coloring->getFingerprint());
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
