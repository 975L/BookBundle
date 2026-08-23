<?php

namespace c975L\BookBundle\Form;

use c975L\BookBundle\Entity\BookVideo;

// A video of a book is hosted as readily as it is uploaded, exactly as a presse row is - without the address field the id could never be typed, and Book:Video's embed was unreachable
class BookVideoType extends BookOwnedMediaType
{
    protected function dataClass(): string
    {
        return BookVideo::class;
    }

    protected function fileLabel(): string
    {
        return 'label.video';
    }

    protected function hasHostedVideo(): bool
    {
        return true;
    }
}
