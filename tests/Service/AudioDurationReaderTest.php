<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Service;

use c975L\BookBundle\Entity\BookEditionFile;
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Service\AudioDurationReader;
use PHPUnit\Framework\TestCase;

// Files built frame by frame: MPEG-1 layer III at 128 kbps and 44.1 kHz, 417 bytes and 1152 samples a frame
class AudioDurationReaderTest extends TestCase
{
    private const string HEADER = "\xFF\xFB\x90\x64";

    private const int FRAME_BYTES = 417;

    private string $path;

    protected function setUp(): void
    {
        $this->path = (string) tempnam(sys_get_temp_dir(), 'mp3');
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        array_map(unlink(...), glob($this->path . '.d/*/*') ?: []);
        array_map(rmdir(...), glob($this->path . '.d/*') ?: []);
        @rmdir($this->path . '.d');
    }

    // A constant bitrate file is counted by its size, after its ID3 tag
    public function testAConstantBitrateFileIsCountedBySize(): void
    {
        $id3 = 'ID3' . "\x04\x00\x00\x00\x00\x00\x0A" . str_repeat("\0", 10);
        file_put_contents($this->path, $id3 . str_repeat($this->frame(), 383));

        $this->assertSame(10, new AudioDurationReader('/public')->seconds($this->path));
    }

    // A tag larger than the window read, as an embedded cover makes it, is skipped rather than searched for a frame
    public function testALargeId3TagIsSkipped(): void
    {
        $size = 100 * 1024;
        $syncsafe = '';
        for ($shift = 21; $shift >= 0; $shift -= 7) {
            $syncsafe .= \chr(($size >> $shift) & 0x7F);
        }
        $id3 = 'ID3' . "\x04\x00\x00" . $syncsafe . str_repeat("\0", $size);
        file_put_contents($this->path, $id3 . str_repeat($this->frame(), 383));

        $this->assertSame(10, new AudioDurationReader('/public')->seconds($this->path));
    }

    // A variable bitrate file is counted by the frame total of its Xing header, whatever its size
    public function testAVariableBitrateFileIsCountedByItsXingHeader(): void
    {
        $xing = self::HEADER . str_repeat("\0", 32) . 'Xing' . "\x00\x00\x00\x01" . pack('N', 2297);
        file_put_contents($this->path, str_pad($xing, self::FRAME_BYTES, "\0") . str_repeat($this->frame(), 3));

        $this->assertSame(60, new AudioDurationReader('/public')->seconds($this->path));
    }

    public function testWhatIsNotAnMp3HasNoDuration(): void
    {
        file_put_contents($this->path, 'OggS not an mp3');

        $reader = new AudioDurationReader('/public');
        $this->assertNull($reader->seconds($this->path));
        $this->assertNull($reader->seconds('/nowhere.mp3'));
    }

    // A stored media is read where the site serves its files, and a row holding no file has no duration
    public function testAMediaIsReadUnderThePublicDirectory(): void
    {
        $project = $this->project('public');
        $reader = new AudioDurationReader($project);

        $this->assertSame(10, $reader->ofMedia(new BookMedia()->setName('/loup.mp3')));
        $this->assertNull($reader->ofMedia(new BookMedia()));
    }

    // An edition's recording is kept out of public/, where the reader looks for it too
    public function testAnEditionsFileIsReadUnderThePrivateDirectory(): void
    {
        $project = $this->project('private');

        $this->assertSame(10, new AudioDurationReader($project)->ofMedia(new BookEditionFile()->setName('loup.mp3')));
    }

    // A project directory holding a ten seconds recording under the given folder
    private function project(string $folder): string
    {
        $project = $this->path . '.d';
        mkdir($project . '/' . $folder, 0o777, true);
        file_put_contents($project . '/' . $folder . '/loup.mp3', str_repeat($this->frame(), 383));

        return $project;
    }

    private function frame(): string
    {
        return str_pad(self::HEADER, self::FRAME_BYTES, "\0");
    }
}
