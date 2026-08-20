<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Form\Block;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ReaderBlockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id', TextType::class, [
                'label' => 'Identifiant du lecteur',
                'help' => 'Sert d\'ancre dans la page, un seul lecteur par identifiant',
                'required' => true,
                'constraints' => [new NotBlank()],
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre de la bande son',
                'required' => false,
            ])
            // Left empty, the pages are turned by the reader alone and the recording plays on its own
            ->add('cues', CollectionType::class, [
                'label' => 'Repères',
                'help' => 'À quelle seconde de la bande son chaque page est tournée',
                'entry_type' => ReaderCueType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'required' => false,
            ])
            ->add('autoAdvance', CheckboxType::class, [
                'label' => 'La voix tourne les pages',
                'help' => 'Décoché, les repères ne servent qu\'à la navigation manuelle',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
