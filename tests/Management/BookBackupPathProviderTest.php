<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Management;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookMarketing;
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Entity\BookPresse;
use c975L\BookBundle\Entity\BookVideo;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\SerieMedia;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Entity\StripMedia;
use c975L\BookBundle\Management\BookBackupPathProvider;
use c975L\ConfigBundle\Management\BackupPath;
use c975L\UiBundle\Contract\VichMediaNamableInterface;
use PHPUnit\Framework\TestCase;

class BookBackupPathProviderTest extends TestCase
{
    // Mirrored, never archived: covers, extracts and recordings are written once, and bzip2 gains about nothing on a webp or a pdf
    public function testTheMediaRootIsMirrored(): void
    {
        $this->assertSame(['public/medias/book' => BackupPath::MODE_MIRROR], $this->paths());
    }

    // The declared root is what every subclass of Media is actually written under - a file stored elsewhere would be backed up nowhere, and nothing would say so
    public function testTheDeclaredRootCoversWhereEveryKindOfMediaIsStored(): void
    {
        foreach ($this->medias() as $media) {
            $path = 'public/' . $media->getVichMediaPath();
            $declared = array_filter(array_keys($this->paths()), static fn (string $root): bool => str_starts_with($path, $root . '/'));

            $this->assertNotEmpty($declared, sprintf('"%s" is backed up nowhere.', $path));
        }
    }

    /** @return array<string, string> */
    private function paths(): array
    {
        $modes = [];
        foreach (new BookBackupPathProvider()->getBackupPaths() as $path) {
            $modes[$path->path] = $path->mode;
        }

        return $modes;
    }

    // One of each of the six subclasses of Media, bound to the owner whose slug builds their path
    /** @return list<VichMediaNamableInterface> */
    private function medias(): array
    {
        $book = new Book()->setSlug('tome-1');
        $serie = new Serie()->setSlug('la-compagnie');
        $strip = new Strip()->setSlug('la-tuile');

        return [
            new BookMedia()->setKind('cover')->setBook($book),
            new BookVideo()->setKind('video')->setBook($book),
            new BookPresse()->setKind('presse')->setBook($book),
            new BookMarketing()->setKind('marketing')->setBook($book),
            new SerieMedia()->setKind('cover')->setSerie($serie),
            new StripMedia()->setKind('plate')->setStrip($strip),
        ];
    }
}
