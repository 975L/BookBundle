<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Form;

use c975L\BookBundle\Entity\BookMedia;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Vich\UploaderBundle\Form\Type\VichFileType;

// The flipbook row: a video, not an image, hence VichFileType where BookCoverType asks for a VichImageType - EasyAdmin's image widget can show nothing of an mp4
class BookFlipbookType extends AbstractType
{
    use MediaFileFieldTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addIdField($builder);

        $builder
            ->add('position', HiddenType::class, [
                'attr' => ['class' => 'ui-sort-position'],
            ])
            ->add('file', VichFileType::class, [
                'label' => 'label.media',
                'required' => false,
                'allow_delete' => true,
                'download_uri' => true,
                'asset_helper' => true,
                'constraints' => [
                    new File(maxSize: '100M', mimeTypes: ['video/mp4'], mimeTypesMessage: 'label.flipbook-help'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BookMedia::class,
            'translation_domain' => 'book',
        ]);
    }
}
