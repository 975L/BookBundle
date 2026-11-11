<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Form;

use c975L\BookBundle\Entity\BookColoring;
use c975L\BookBundle\Entity\Media;
use c975L\BookBundle\Service\BookColoringPalette;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use c975L\BookBundle\Service\BookTranslator;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\File;

// A drawing to colour on screen: a rank, a title and the PNG like every file a book owns, plus the day it comes out, the level it is grouped under on a site naming some, the same as its documents to print (see BookCustomizationProviderInterface::getPrintableKinds()), and the coloured illustration its crayons are read off - read once, on the save it is sent with, and never stored (see BookColoringPalette)
class BookColoringType extends BookOwnedMediaType
{
    public function __construct(
        private readonly BookColoringPalette $palette,
        private readonly BookCustomizationRegistry $customizationRegistry,
        ?BookTranslator $bookTranslator = null,
    ) {
        parent::__construct($bookTranslator);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        // A language screen offers the title alone: the day and the crayons are the same in every language
        if (null !== ($options['translation_locale'] ?? null)) {
            return;
        }

        $builder
            ->add('publishedOn', DateType::class, [
                'label' => 'label.printable_published_on',
                'help' => 'label.coloring_published_on-help',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('source', FileType::class, [
                'label' => 'label.coloring_source',
                'help' => 'label.coloring_source-help',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File(maxSize: '20M', extensions: array_fill_keys(['jpg', 'jpeg', 'png', 'webp'], Media::IMAGE_UPLOAD_TYPES)),
                ],
            ])
        ;

        $kinds = $this->customizationRegistry->getPrintableKinds();
        if ([] !== $kinds) {
            $builder->add('kind', ChoiceType::class, [
                'label' => 'label.printable_kind',
                'required' => false,
                'choices' => array_flip($kinds),
            ]);
        }

        // An illustration that cannot be read leaves the crayons as they were, its constraint telling the editor why
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (PostSubmitEvent $event): void {
            $coloring = $event->getData();
            $file = $event->getForm()->get('source')->getData();
            $palette = $file instanceof UploadedFile ? $this->palette->fromFile($file->getPathname()) : [];
            if ($coloring instanceof BookColoring && [] !== $palette) {
                $coloring->setPalette($palette);
            }
        });
    }

    protected function dataClass(): string
    {
        return BookColoring::class;
    }

    protected function fileLabel(): string
    {
        return 'label.coloring';
    }
}
