<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

use c975L\UiBundle\Contract\BundleScriptProviderInterface;

// The book_reader block's controller, which its own page never loads on its own - declaring the entrypoint in importmap.php only makes it servable, the front layout still has to name it
class ScriptProvider implements BundleScriptProviderInterface
{
    public function getScripts(): array
    {
        return [
            '@c975l/book-bundle/controllers.js',
        ];
    }
}
