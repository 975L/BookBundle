<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Enum;

use c975L\BookBundle\Enum\BookSubjectScheme;
use PHPUnit\Framework\TestCase;

class BookSubjectSchemeTest extends TestCase
{
    // ONIX code list 27: a Thema qualifier is a scheme of its own, told by its first digit
    public function testEachCodeIsWrittenUnderItsOnixScheme(): void
    {
        $this->assertSame('29', BookSubjectScheme::Clil->onixScheme('3730'));
        $this->assertSame('10', BookSubjectScheme::Bisac->onixScheme('JUV010000'));
        $this->assertSame('93', BookSubjectScheme::Thema->onixScheme('YBCS1'));
        $this->assertSame('94', BookSubjectScheme::Thema->onixScheme('1DDF-FR-VH'));
        $this->assertSame('96', BookSubjectScheme::Thema->onixScheme('3MPBLB'));
        $this->assertSame('98', BookSubjectScheme::Thema->onixScheme('5AC'));
    }
}
