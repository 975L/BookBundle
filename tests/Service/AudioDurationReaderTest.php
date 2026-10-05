<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Service;

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
    }

    // A constant bitrate file is counted by its size, after its ID3 tag
    public function testAConstantBitrateFileIsCountedBySize(): void
    {
        $id3 = 'ID3' . "\x04\x00\x00\x00\x00\x00\x0A" . str_repeat("\0", 10);
        file_put_contents($this->path, $id3 . str_repeat($this->frame(), 383));

        $this->assertSame(10, new AudioDurationReader()->seconds($this->path));
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

        $this->assertSame(10, new AudioDurationReader()->seconds($this->path));
    }

    // A variable bitrate file is counted by the frame total of its Xing header, whatever its size
    public function testAVariableBitrateFileIsCountedByItsXingHeader(): void
    {
        $xing = self::HEADER . str_repeat("\0", 32) . 'Xing' . "\x00\x00\x00\x01" . pack('N', 2297);
        file_put_contents($this->path, str_pad($xing, self::FRAME_BYTES, "\0") . str_repeat($this->frame(), 3));

        $this->assertSame(60, new AudioDurationReader()->seconds($this->path));
    }

    public function testWhatIsNotAnMp3HasNoDuration(): void
    {
        file_put_contents($this->path, 'OggS not an mp3');

        $reader = new AudioDurationReader();
        $this->assertNull($reader->seconds($this->path));
        $this->assertNull($reader->seconds('/nowhere.mp3'));
    }

    private function frame(): string
    {
        return str_pad(self::HEADER, self::FRAME_BYTES, "\0");
    }
}
