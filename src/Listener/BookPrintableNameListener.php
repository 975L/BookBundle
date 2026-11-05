<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Listener;

use c975L\BookBundle\Entity\BookPrintable;
use c975L\BookBundle\Service\BookPrintableNamer;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Vich\UploaderBundle\Event\Event;

// Hands a document to print its prefix right before UiMediaNamer names it: Vich fires this for every upload, the form's as the import's and the duplicator's, and the entity holds no service to ask itself
#[AsEventListener(event: 'vich_uploader.pre_upload', method: 'onPreUpload')]
class BookPrintableNameListener
{
    public function __construct(private readonly BookPrintableNamer $printableNamer)
    {
    }

    // Any other media goes by the name its own getVichMediaPath() gives it
    public function onPreUpload(Event $event): void
    {
        $printable = $event->getObject();
        if ($printable instanceof BookPrintable) {
            $printable->setNamePrefix($this->printableNamer->prefix($printable));
        }
    }
}
