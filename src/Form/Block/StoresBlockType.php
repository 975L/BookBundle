<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Form\Block;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;

// The data sub-form of the "book_stores" kind - where the catalog is sold, said once for the whole site. The head it shares with every listing is in AbstractBookListingBlockType; no selection field, this kind querying nothing
class StoresBlockType extends AbstractBookListingBlockType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        // The tile a book's page prints, name under the mark - or the wide plate the mark stands in alone, which is what a rail closing a page wants: the brands are read at a glance and their names say nothing more
        $builder
            ->add('variant', ChoiceType::class, [
                'label' => 'label.block_stores_variant',
                'required' => false,
                'choices' => [
                    'label.block_stores_variant_tiles' => '',
                    'label.block_stores_variant_logos' => 'logos',
                ],
                'placeholder' => false,
            ])
        ;

        $builder->add('items', CollectionType::class, [
            'label' => 'label.block_stores_items',
            'entry_type' => StoreItemType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'prototype' => true,
        ]);
    }
}
