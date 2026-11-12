<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Service;

use c975L\BookBundle\Service\BookColoringPalette;
use PHPUnit\Framework\TestCase;

// The crayons read off an illustration: its bright colours, apart from each other and livened up, in the order of the rainbow, then the three fixed ones
class BookColoringPaletteTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'palette');
    }

    protected function tearDown(): void
    {
        unlink($this->path);
    }

    // An image of vertical bands, one colour each
    /** @param list<array{int, int, int}> $bands */
    private function image(array $bands): string
    {
        $width = 10 * \count($bands);
        $image = imagecreatetruecolor($width, 20);
        foreach ($bands as $index => [$red, $green, $blue]) {
            imagefilledrectangle($image, 10 * $index, 0, 10 * $index + 9, 19, (int) imagecolorallocate($image, $red, $green, $blue));
        }
        imagepng($image, $this->path);

        return $this->path;
    }

    public function testAColourfulImageGivesEighteenCrayonsThenTheFixedOnes(): void
    {
        $bands = [];
        for ($hue = 0; $hue < 360; $hue += 10) {
            $bands[] = $this->rgb($hue);
        }

        $palette = new BookColoringPalette()->fromFile($this->image($bands));

        $this->assertCount(BookColoringPalette::COLOURS + 3, $palette);
        $this->assertSame(BookColoringPalette::FIXED, \array_slice($palette, -3));
        $this->assertCount(\count($palette), array_unique($palette));
        foreach ($palette as $colour) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $colour);
        }
    }

    // A grey illustration has nothing to give: the fixed colours alone, never an invented one
    public function testAGreyImageGivesTheFixedColoursAlone(): void
    {
        $this->assertSame(BookColoringPalette::FIXED, new BookColoringPalette()->fromFile($this->image([[128, 128, 128], [20, 20, 20], [250, 250, 250]])));
    }

    // Pure black and pure white, the lines and the paper of a colouring page, are greys too
    public function testPureBlackAndWhiteGiveTheFixedColoursAlone(): void
    {
        $this->assertSame(BookColoringPalette::FIXED, new BookColoringPalette()->fromFile($this->image([[0, 0, 0], [255, 255, 255]])));
    }

    // A dull colour comes out as a crayon: saturated enough, and in the order of the rainbow
    public function testDullColoursAreLivenedAndSortedByHue(): void
    {
        $palette = new BookColoringPalette()->fromFile($this->image([[60, 60, 140], [140, 60, 60]]));

        $this->assertSame(['#a02828', '#2828a0'], \array_slice($palette, 0, 2));
    }

    // A dark red and a mid red livened to the same crayon are given once
    public function testTwoColoursLivenedAlikeGiveOneCrayon(): void
    {
        $palette = new BookColoringPalette()->fromFile($this->image([[140, 40, 40], [60, 20, 20]]));

        $this->assertCount(1 + 3, $palette);
    }

    public function testAFileThatIsNoImageGivesNothing(): void
    {
        file_put_contents($this->path, 'not an image');

        $this->assertSame([], new BookColoringPalette()->fromFile($this->path));
        $this->assertSame([], new BookColoringPalette()->fromFile($this->path . '-missing'));
    }

    // A pure colour of that hue
    /** @return array{int, int, int} */
    private function rgb(int $hue): array
    {
        $second = (int) round(255 * (1 - abs(fmod($hue / 60, 2) - 1)));

        return match (intdiv($hue, 60)) {
            0 => [255, $second, 0],
            1 => [$second, 255, 0],
            2 => [0, 255, $second],
            3 => [0, $second, 255],
            4 => [$second, 0, 255],
            default => [255, 0, $second],
        };
    }
}
