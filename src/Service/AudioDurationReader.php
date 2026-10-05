<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

// How long a recording plays, read from the file itself rather than typed in: a duration kept by hand drifts the day the file is replaced. MP3 only, read from its first frame - no getID3, no ffprobe, neither being sure to exist on a managed host
class AudioDurationReader
{
    // Kilobits per second by MPEG version (1, then 2 and 2.5) and layer (I, II, III), indexed by the frame header's bitrate bits
    private const array BITRATES = [
        1 => [
            1 => [0, 32, 64, 96, 128, 160, 192, 224, 256, 288, 320, 352, 384, 416, 448],
            2 => [0, 32, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320, 384],
            3 => [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320],
        ],
        2 => [
            1 => [0, 32, 48, 56, 64, 80, 96, 112, 128, 144, 160, 176, 192, 224, 256],
            2 => [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160],
            3 => [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160],
        ],
    ];

    // Hertz by MPEG version, indexed by the frame header's sample rate bits
    private const array SAMPLE_RATES = [
        1 => [44100, 48000, 32000],
        2 => [22050, 24000, 16000],
        25 => [11025, 12000, 8000],
    ];

    // Bytes of side information after the header, by MPEG version (1, then 2 and 2.5), stereo then mono
    private const array SIDE_INFORMATION = [1 => [32, 17], 2 => [17, 9]];

    // Whole seconds, null for a file that is not an MP3 or cannot be read. A variable bitrate file is counted by the frame total its Xing or VBRI header carries, a constant one by its size
    public function seconds(string $path): ?int
    {
        $handle = is_file($path) ? fopen($path, 'r') : false;
        if (false === $handle) {
            return null;
        }

        // The audio is read after the ID3 tag, which an embedded cover can grow well past the window read
        $offset = $this->skipId3((string) fread($handle, 10));
        fseek($handle, $offset);
        $head = (string) fread($handle, 65536);
        fclose($handle);

        $frame = $this->firstFrame($head, 0);
        if (null === $frame) {
            return null;
        }

        $frames = $this->vbrFrameCount($head, $frame);
        if (null !== $frames) {
            return (int) round($frames * $frame['samples'] / $frame['rate']);
        }

        $bytes = (int) filesize($path) - $offset - $frame['offset'];

        return (int) round($bytes * 8 / ($frame['bitrate'] * 1000));
    }

    // Where the audio starts: after an ID3v2 tag, whose size is written on 4 bytes of 7 bits
    private function skipId3(string $head): int
    {
        if (!str_starts_with($head, 'ID3') || \strlen($head) < 10) {
            return 0;
        }

        $size = 0;
        for ($i = 6; $i < 10; ++$i) {
            $size = ($size << 7) | (\ord($head[$i]) & 0x7F);
        }

        return 10 + $size + ((\ord($head[5]) & 0x10) ? 10 : 0);
    }

    // The first frame header found from $offset, with what the duration is computed from
    /** @return array{offset: int, version: int, layer: int, mono: bool, bitrate: int, rate: int, samples: int}|null */
    private function firstFrame(string $head, int $offset): ?array
    {
        $length = \strlen($head) - 4;

        for ($i = $offset; $i < $length; ++$i) {
            if ("\xFF" !== $head[$i] || (\ord($head[$i + 1]) & 0xE0) !== 0xE0) {
                continue;
            }

            $b1 = \ord($head[$i + 1]);
            $b2 = \ord($head[$i + 2]);
            $versionBits = ($b1 >> 3) & 0x03;
            $layerBits = ($b1 >> 1) & 0x03;
            $bitrateIndex = $b2 >> 4;
            $rateIndex = ($b2 >> 2) & 0x03;

            // Reserved values: not a frame, a byte of something else that happens to look like one
            if (1 === $versionBits || 0 === $layerBits || 0 === $bitrateIndex || 15 === $bitrateIndex || 3 === $rateIndex) {
                continue;
            }

            $version = match ($versionBits) {
                3 => 1,
                2 => 2,
                default => 25,
            };
            $layer = 4 - $layerBits;

            return [
                'offset' => $i,
                'version' => $version,
                'layer' => $layer,
                'mono' => 3 === (\ord($head[$i + 3]) >> 6),
                'bitrate' => self::BITRATES[1 === $version ? 1 : 2][$layer][$bitrateIndex],
                'rate' => self::SAMPLE_RATES[$version][$rateIndex],
                'samples' => match (true) {
                    1 === $layer => 384,
                    3 === $layer && 1 !== $version => 576,
                    default => 1152,
                },
            ];
        }

        return null;
    }

    // The frame total a variable bitrate encoder writes in the first frame: "Xing"/"Info" after the side information, "VBRI" 32 bytes after the header
    /** @param array{offset: int, version: int, mono: bool} $frame */
    private function vbrFrameCount(string $head, array $frame): ?int
    {
        $xing = $frame['offset'] + 4 + self::SIDE_INFORMATION[1 === $frame['version'] ? 1 : 2][(int) $frame['mono']];
        $tag = substr($head, $xing, 4);

        if (\in_array($tag, ['Xing', 'Info'], true) && (\ord(substr($head, $xing + 7, 1)) & 0x01)) {
            return $this->readCount($head, $xing + 8);
        }

        $vbri = $frame['offset'] + 36;

        return 'VBRI' === substr($head, $vbri, 4) ? $this->readCount($head, $vbri + 14) : null;
    }

    // A frame total, written on 4 bytes big-endian
    private function readCount(string $head, int $offset): ?int
    {
        $bytes = substr($head, $offset, 4);

        return 4 === \strlen($bytes) ? unpack('N', $bytes)[1] : null;
    }
}
