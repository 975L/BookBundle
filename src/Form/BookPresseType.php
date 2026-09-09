<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Form;

use c975L\BookBundle\Entity\BookPresse;

// A press article is an uploaded file as well as a player address: a filmed review is pasted like a video of the book (see BookVideoType)
class BookPresseType extends BookOwnedMediaType
{
    protected function dataClass(): string
    {
        return BookPresse::class;
    }

    protected function fileLabel(): string
    {
        return 'label.presse';
    }

    protected function hasHostedVideo(): bool
    {
        return true;
    }
}
