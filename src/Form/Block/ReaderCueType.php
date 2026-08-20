<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Form\Block;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

// One page of the album and the second of the recording it is turned at
class ReaderCueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('page', IntegerType::class, [
                'label' => 'Page',
                'required' => true,
                'attr' => ['min' => 1],
                'constraints' => [new NotBlank()],
            ])
            ->add('start', NumberType::class, [
                'label' => 'Seconde de la bande son',
                'required' => true,
                'scale' => 1,
                'attr' => ['min' => 0, 'step' => 0.1],
                'constraints' => [new NotBlank(), new PositiveOrZero()],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
