<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Listener;

use c975L\BookBundle\Entity\BookColoring;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Event\Event;

// Stamps a drawing to colour with the hash of its bytes right before it is stored: Vich fires this for every upload, the form's as the import's and the duplicator's, so a replaced drawing always says so, and a title or a day edited alone never does
#[AsEventListener(event: 'vich_uploader.pre_upload', method: 'onPreUpload')]
class BookColoringFingerprintListener
{
    // Any other media carries no fingerprint
    public function onPreUpload(Event $event): void
    {
        $coloring = $event->getObject();
        $file = $coloring instanceof BookColoring ? $coloring->getFile() : null;
        if ($file instanceof File && is_file($file->getPathname())) {
            $coloring->setFingerprint(hash_file('xxh128', $file->getPathname()) ?: null);
        }
    }
}
