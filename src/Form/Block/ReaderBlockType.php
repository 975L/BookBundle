<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
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
                'label' => 'label.block_reader_id',
                'help' => 'label.block_reader_id_help',
                'required' => true,
                'constraints' => [new NotBlank()],
            ])
            ->add('title', TextType::class, [
                'label' => 'label.block_reader_title',
                'required' => false,
            ])
            // Left empty, the pages are turned by the reader alone and the recording plays on its own
            ->add('cues', CollectionType::class, [
                'label' => 'label.block_reader_cues',
                'help' => 'label.block_reader_cues_help',
                'entry_type' => ReaderCueType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'required' => false,
            ])
            ->add('autoAdvance', CheckboxType::class, [
                'label' => 'label.block_reader_auto_advance',
                'help' => 'label.block_reader_auto_advance_help',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'book',
        ]);
    }
}
