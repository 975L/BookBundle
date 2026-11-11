<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Listener;

use c975L\BookBundle\Entity\BookColoring;
use c975L\BookBundle\Listener\BookColoringFingerprintListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Mapping\PropertyMapping;

// A drawing to colour is stamped with the hash of the bytes being stored
class BookColoringFingerprintListenerTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'fingerprint');
        file_put_contents($this->path, 'drawing');
    }

    protected function tearDown(): void
    {
        unlink($this->path);
    }

    public function testADrawingIsStampedWithItsBytes(): void
    {
        $coloring = new BookColoring()->setFile(new File($this->path));

        new BookColoringFingerprintListener()->onPreUpload(new Event($coloring, new PropertyMapping('file', 'name')));

        $this->assertSame(hash('xxh128', 'drawing'), $coloring->getFingerprint());
    }

    // Replaced, the drawing says so: the same row, another fingerprint
    public function testAReplacedDrawingChangesItsFingerprint(): void
    {
        $coloring = new BookColoring()->setFile(new File($this->path));
        $listener = new BookColoringFingerprintListener();
        $listener->onPreUpload(new Event($coloring, new PropertyMapping('file', 'name')));
        $first = $coloring->getFingerprint();

        file_put_contents($this->path, 'another drawing');
        $listener->onPreUpload(new Event($coloring, new PropertyMapping('file', 'name')));

        $this->assertNotSame($first, $coloring->getFingerprint());
    }

    // A row whose file is not being replaced keeps the fingerprint it had
    public function testARowWithoutAFileKeepsItsFingerprint(): void
    {
        $coloring = new BookColoring()->setFingerprint('kept');

        new BookColoringFingerprintListener()->onPreUpload(new Event($coloring, new PropertyMapping('file', 'name')));

        $this->assertSame('kept', $coloring->getFingerprint());
    }
}
