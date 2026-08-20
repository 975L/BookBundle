<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Management;

use c975L\BookBundle\Entity\Media;
use c975L\ConfigBundle\Management\BackupPath;
use c975L\ConfigBundle\Management\BackupPathProviderInterface;

// Where the covers, extracts, press clippings and marketing files land, the only content of this bundle neither a git clone nor a database dump brings back - ConfigBundle backs up nothing it wasn't declared, so staying silent here means a catalog backed up nowhere
class BookBackupPathProvider implements BackupPathProviderInterface
{
    // A single root rather than one path per kind: the six subclasses of Media all write under it (see their getVichMediaPath), and the collector drops any path nested in another already declared
    public function getBackupPaths(): array
    {
        return [
            // Mirrored rather than archived: covers and extracts are written once, and bzip2 gains about nothing on a webp or a pdf
            new BackupPath('public/' . Media::MEDIA_DIRECTORY, BackupPath::MODE_MIRROR),
        ];
    }
}
