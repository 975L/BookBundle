<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Assets;

use PHPUnit\Framework\TestCase;

// One Stimulus application per page. startStimulusApp() does not only start an application: it also registers whatever
// the consuming app's controllers.json enables, "live" and "chart" among them. A page loading several c975L barrels,
// each starting its own, therefore built those controllers once per barrel - a Live Component answered as many
// requests and morphed its results in as many times. The repository has no browser to catch that in
class StimulusAppSharingTest extends TestCase
{
    private const array BARRELS = ['assets/controllers.js'];

    public function testEveryBarrelJoinsTheSharedApplication(): void
    {
        foreach (self::BARRELS as $barrel) {
            $source = $this->read($barrel);

            $this->assertStringContainsString(
                'globalThis.c975lStimulusApp ??= startStimulusApp()',
                $source,
                sprintf('"%s" starts an application of its own instead of joining the page\'s.', $barrel)
            );
        }
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
