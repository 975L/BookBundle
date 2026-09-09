<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Form;

use c975L\BookBundle\Entity\StripMedia;
use c975L\BookBundle\Enum\StripMediaKind;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Vich\UploaderBundle\Form\Type\VichImageType;

class StripMediaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('position', HiddenType::class, [
                'attr' => ['class' => 'ui-sort-position'],
            ])
        ;

        // Offered only where the collection does not say the role itself: the three collections named after a role write their own (see Strip::addCaseMedia()), and a choice there would let a file filed under one heading claim another. The view holding what no heading claims is the one place a role is picked by hand, and it is how a media uploaded before the roles existed is filed
        if ($options['kind_editable']) {
            $builder->add('kind', ChoiceType::class, [
                'label' => 'label.kind',
                'required' => false,
                'placeholder' => 'label.strip_media_gallery',
                'choices' => StripMediaKind::choices(),
            ]);
        }

        $builder
            ->add('file', VichImageType::class, [
                'label' => 'label.media',
                'required' => false,
                'allow_delete' => true,
                'download_uri' => true,
                'asset_helper' => true,
                'constraints' => [
                    new File(maxSize: '100M'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => StripMedia::class,
            'translation_domain' => 'book',
            'kind_editable' => false,
        ]);
        $resolver->setAllowedTypes('kind_editable', 'bool');
    }
}
