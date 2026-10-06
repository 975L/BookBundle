<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Enum;

use c975L\BookBundle\Enum\BookEditionKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BookEditionKindTest extends TestCase
{
    // The site's own word is read by what it names: a recording, a file, or else a printed book
    #[DataProvider('kinds')]
    public function testAKindIsReadByItsWords(?string $kind, BookEditionKind $expected): void
    {
        $this->assertSame($expected, BookEditionKind::of($kind));
    }

    public static function kinds(): iterable
    {
        yield 'paper' => ['paper', BookEditionKind::Paper];
        yield 'audio' => ['audio', BookEditionKind::Audio];
        yield 'digital' => ['digital', BookEditionKind::Digital];
        yield 'original digital' => ['original_digital', BookEditionKind::Digital];
        yield 'ebook in capitals' => ['EBOOK', BookEditionKind::Digital];
        yield 'epub' => ['illustrated-epub', BookEditionKind::Digital];
        yield 'pdf' => ['pdf', BookEditionKind::Digital];
        yield 'unknown word' => ['hardcover', BookEditionKind::Paper];
        yield 'no kind' => [null, BookEditionKind::Paper];
    }

    public function testTheDefaultsAreTheThreeKindsUnderTheirLabels(): void
    {
        $this->assertSame(['paper' => 'label.edition_paper', 'digital' => 'label.edition_digital', 'audio' => 'label.edition_audio'], BookEditionKind::defaults());
    }
}
