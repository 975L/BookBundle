<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Form;

use c975L\BookBundle\Entity\BookVideo;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;

// A video of a book is hosted as readily as it is uploaded, exactly as a presse row is - without the address field the id could never be typed, and Book:Video's embed was unreachable
class BookVideoType extends BookOwnedMediaType
{
    // The day the video went online, which dates its VideoObject: a video of a book is the one row published as such, so a presse row has no use for it. Left out of a language screen, a date being the same in every language
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        if (null === ($options['translation_locale'] ?? null)) {
            $builder->add('uploadDate', DateType::class, [
                'label' => 'label.video_upload_date',
                'help' => 'label.video_upload_date-help',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ]);
        }
    }

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
