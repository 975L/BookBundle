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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class SerieStripsBlockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('serieSlug', TextType::class, [
                'label' => 'Slug de la série',
                'required' => true,
                'constraints' => [new NotBlank()],
            ])
            ->add('max', IntegerType::class, [
                'label' => 'Nombre de strips (vide = tous)',
                'required' => false,
                'attr' => ['min' => 1],
            ])
        ;
    }
}
