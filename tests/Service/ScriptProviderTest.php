<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Service;

use c975L\BookBundle\Service\ScriptProvider;
use PHPUnit\Framework\TestCase;

class ScriptProviderTest extends TestCase
{
    // The bundle's own front-end controllers.js is contributed as a front script - without it the reader's pages never turn
    public function testGetScriptsReturnsBundleFrontController(): void
    {
        $this->assertSame(['@c975l/book-bundle/controllers.js'], new ScriptProvider()->getScripts());
    }
}
