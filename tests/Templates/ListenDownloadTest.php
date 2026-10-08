<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Templates;

use PHPUnit\Framework\TestCase;

// The listening page hands UiBundle's download button every file its player needs again offline. Nothing renders here, so the call is read where it is written
class ListenDownloadTest extends TestCase
{
    private const string DISPLAY = __DIR__ . '/../../templates/listen/display.html.twig';

    public function testThePageMountsTheDownloadButton(): void
    {
        $this->assertStringContainsString('<twig:c975LUi:Pwa:Download name="book-listen-{{ book.id }}"', $this->contents());
    }

    // The recording, the pages and their timecodes: a story missing one of them plays wrong in airplane mode
    public function testTheRecordingThePagesAndTheirTimecodesAreKept(): void
    {
        $contents = $this->contents();

        $this->assertStringContainsString('{% set offlineUrls = [vich_uploader_asset(audio), coverUrl] %}', $contents);
        $this->assertStringContainsString('book.pages|map(page => vich_uploader_asset(page))', $contents);
        $this->assertStringContainsString('vich_uploader_asset(book.cues)', $contents);
    }

    private function contents(): string
    {
        $this->assertFileExists(self::DISPLAY);

        return (string) file_get_contents(self::DISPLAY);
    }
}
