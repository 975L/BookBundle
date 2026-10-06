<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Enum;

use c975L\BookBundle\Enum\BookChannel;
use PHPUnit\Framework\TestCase;

class BookChannelTest extends TestCase
{
    // Offered to a choice field as label => value, every channel included
    public function testEveryChannelIsOfferedUnderItsLabel(): void
    {
        $this->assertSame([
            'label.channel_shop' => 'shop',
            'label.channel_onix' => 'onix',
            'label.channel_google' => 'google',
            'label.channel_apple' => 'apple',
        ], BookChannel::choices());
    }
}
