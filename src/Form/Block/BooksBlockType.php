<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Form\Block;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

// The data sub-form of the "book_books" kind - the head it shares with every other listing is in AbstractBookListingBlockType
class BooksBlockType extends AbstractBookListingBlockType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        // The catalog the listing draws from, narrowed down to one category - by slug and not by a picker, block data being stored as JSON and holding no entity (see SerieStripsBlockType, which names its serie the same way). Left empty, the whole catalog
        $builder
            ->add('categorySlug', TextType::class, [
                'label' => 'label.block_category_slug',
                'help' => 'label.block_category_slug_help',
                'required' => false,
            ])
        ;

        $this->addSelectionFields($builder, 'label.block_max_books');
        $this->addDisplayMoreField($builder, 'label.block_display_more_books');
    }
}
