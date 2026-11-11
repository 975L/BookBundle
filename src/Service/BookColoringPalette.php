<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

// The crayons of a drawing to colour, read off the coloured illustration it was drawn from: its bright colours grouped by hue and lightness, the most present kept apart from each other, then livened up so they read as crayons and not as a photograph, in the order of the rainbow. A dark illustration narrows the gap allowed between two until enough are found. Black, a dark grey and a light grey always close the list
class BookColoringPalette
{
    // How many colours the illustration gives, before the three fixed ones
    public const int COLOURS = 18;

    // What every drawing gets, whatever its illustration
    public const array FIXED = ['#1a1a1a', '#555a66', '#b8bcc6'];

    // Width the illustration is read at: enough to weigh its colours, a few thousand pixels at most
    private const int SAMPLE_WIDTH = 160;

    // The gap, in RGB distance, two kept colours start apart at, narrowed step by step down to the last
    private const array GAPS = [70, 55, 40, 28, 18, 10, 0];

    // The palette of the image at that path, empty when it cannot be read as one
    /** @return list<string> */
    public function fromFile(string $path): array
    {
        $bytes = is_file($path) ? file_get_contents($path) : false;
        $image = false === $bytes || '' === $bytes ? false : @imagecreatefromstring($bytes);
        if (false === $image) {
            return [];
        }

        $sample = imagescale($image, min(self::SAMPLE_WIDTH, imagesx($image)));
        if (false === $sample) {
            return [];
        }

        $picked = $this->pick($this->buckets($sample));

        // Two colours apart can liven into the same crayon, a dark one and a mid one of a hue both raised to the same lightness
        return [...$this->sortByHue(array_values(array_unique(array_map($this->liven(...), $picked)))), ...self::FIXED];
    }

    // The bright pixels grouped by hue (10° wide) and lightness (three bands), each group's mean colour, most present first
    /** @return list<array{int, int, int}> */
    private function buckets(\GdImage $image): array
    {
        $groups = [];
        for ($y = 0, $height = imagesy($image); $y < $height; ++$y) {
            for ($x = 0, $width = imagesx($image); $x < $width; ++$x) {
                $rgb = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                [$hue, $saturation, $lightness] = $this->toHsl($rgb['red'], $rgb['green'], $rgb['blue']);

                // Greys, near blacks and near whites left out: the fixed colours stand for them
                if ($saturation < 0.25 || $lightness < 0.12 || $lightness > 0.92) {
                    continue;
                }

                $key = intdiv((int) $hue, 10) . '-' . min(2, (int) ($lightness * 3));
                $groups[$key] ??= [0, 0, 0, 0];
                $groups[$key][0] += $rgb['red'];
                $groups[$key][1] += $rgb['green'];
                $groups[$key][2] += $rgb['blue'];
                ++$groups[$key][3];
            }
        }

        usort($groups, static fn (array $a, array $b): int => $b[3] <=> $a[3]);

        return array_map(static fn (array $group): array => [intdiv($group[0], $group[3]), intdiv($group[1], $group[3]), intdiv($group[2], $group[3])], $groups);
    }

    // The most present colours kept apart from each other, the gap narrowing until enough are found or none is left
    /**
     * @param list<array{int, int, int}> $candidates
     *
     * @return list<array{int, int, int}>
     */
    private function pick(array $candidates): array
    {
        $picked = [];
        foreach (self::GAPS as $gap) {
            foreach ($candidates as $candidate) {
                if (\count($picked) >= self::COLOURS) {
                    return $picked;
                }
                if (!\in_array($candidate, $picked, true) && $this->farFrom($candidate, $picked, $gap)) {
                    $picked[] = $candidate;
                }
            }
        }

        return $picked;
    }

    // Whether a colour stands at least that gap from every one already kept
    /**
     * @param array{int, int, int}       $colour
     * @param list<array{int, int, int}> $others
     */
    private function farFrom(array $colour, array $others, int $gap): bool
    {
        return array_all($others, static fn (array $other): bool => sqrt(($colour[0] - $other[0]) ** 2 + ($colour[1] - $other[1]) ** 2 + ($colour[2] - $other[2]) ** 2) >= $gap);
    }

    // Saturated and neither too dark nor too pale, as a crayon is
    /** @param array{int, int, int} $rgb */
    private function liven(array $rgb): string
    {
        [$hue, $saturation, $lightness] = $this->toHsl(...$rgb);

        return $this->toHex($hue, max($saturation, 0.6), min(0.8, max(0.38, $lightness)));
    }

    // In the order of the rainbow, as a box of crayons is laid out
    /**
     * @param list<string> $colours
     *
     * @return list<string>
     */
    private function sortByHue(array $colours): array
    {
        usort($colours, fn (string $a, string $b): int => $this->hueOf($a) <=> $this->hueOf($b));

        return $colours;
    }

    // The hue, in degrees, of a "#rrggbb" colour
    private function hueOf(string $hex): float
    {
        return $this->toHsl((int) hexdec(substr($hex, 1, 2)), (int) hexdec(substr($hex, 3, 2)), (int) hexdec(substr($hex, 5, 2)))[0];
    }

    // Hue in degrees, saturation and lightness from 0 to 1
    /** @return array{float, float, float} */
    private function toHsl(int $red, int $green, int $blue): array
    {
        $r = $red / 255;
        $g = $green / 255;
        $b = $blue / 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $lightness = ($max + $min) / 2;
        $delta = $max - $min;
        if (0.0 === $delta) {
            return [0.0, 0.0, $lightness];
        }

        $saturation = $delta / (1 - abs(2 * $lightness - 1));
        $hue = match ($max) {
            $r => fmod(($g - $b) / $delta + 6, 6),
            $g => ($b - $r) / $delta + 2,
            default => ($r - $g) / $delta + 4,
        };

        return [$hue * 60, min(1.0, $saturation), $lightness];
    }

    // Back from hue, saturation and lightness to "#rrggbb"
    private function toHex(float $hue, float $saturation, float $lightness): string
    {
        $chroma = (1 - abs(2 * $lightness - 1)) * $saturation;
        $second = $chroma * (1 - abs(fmod($hue / 60, 2) - 1));
        $match = $lightness - $chroma / 2;
        [$r, $g, $b] = match (intdiv((int) $hue, 60) % 6) {
            0 => [$chroma, $second, 0],
            1 => [$second, $chroma, 0],
            2 => [0, $chroma, $second],
            3 => [0, $second, $chroma],
            4 => [$second, 0, $chroma],
            default => [$chroma, 0, $second],
        };

        return \sprintf('#%02x%02x%02x', (int) round(($r + $match) * 255), (int) round(($g + $match) * 255), (int) round(($b + $match) * 255));
    }
}
