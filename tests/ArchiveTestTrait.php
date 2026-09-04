<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests;

use c975L\BookBundle\Entity\Media;

// Shared by every test of the export and import providers - the two directories they work between: the site's own project directory, where the stored files live under public/, and the one an uploaded zip was extracted into (see ConfigBundle's ContentImportController)
trait ArchiveTestTrait
{
    /** @param array<string, string> $files stored name (relative to public/) => bytes */
    private function createProjectDir(array $files): string
    {
        $projectDir = sys_get_temp_dir() . '/book_archive_test_' . bin2hex(random_bytes(4));
        mkdir($projectDir . '/public/' . Media::MEDIA_DIRECTORY, 0777, true);
        foreach ($files as $name => $content) {
            $this->write($projectDir . '/public/' . $name, $content);
        }

        return $projectDir;
    }

    // Plays what ContentImportController does with a zip: the archive's entries laid out under one dir, keyed by the very path the exported items point at
    /** @param array<string, string> $files archive-relative path => disk path, as an export provider returns them */
    private function extractArchive(array $files): string
    {
        $filesDir = sys_get_temp_dir() . '/book_archive_test_' . bin2hex(random_bytes(4));
        foreach ($files as $archivePath => $diskPath) {
            $this->write($filesDir . '/' . $archivePath, file_get_contents($diskPath));
        }

        return $filesDir;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $paths = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($paths as $path) {
            $path->isDir() ? rmdir($path->getPathname()) : unlink($path->getPathname());
        }
        rmdir($dir);
    }

    private function write(string $path, string $content): void
    {
        if (!is_dir(\dirname($path))) {
            mkdir(\dirname($path), 0777, true);
        }
        file_put_contents($path, $content);
    }
}
