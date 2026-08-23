<?php

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
