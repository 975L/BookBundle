<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Form;

use c975L\BookBundle\Entity\BookMarketing;

// A promotional visual is always an uploaded file: nothing to host elsewhere, hence no address field
class BookMarketingType extends BookOwnedMediaType
{
    protected function dataClass(): string
    {
        return BookMarketing::class;
    }

    protected function fileLabel(): string
    {
        return 'label.marketing';
    }
}
