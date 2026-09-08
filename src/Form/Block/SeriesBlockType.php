<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Form\Block;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

// The data sub-form of the "book_series" kind - the head it shares with every other listing is in AbstractBookListingBlockType
class SeriesBlockType extends AbstractBookListingBlockType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        // The series the block draws, named one by one - by slug and not by a picker, block data being stored as JSON and holding no entity (the same way SerieStripsBlockType names its serie). Left empty, the whole catalog, which is what every block stored before this field existed shows
        $builder
            ->add('serieSlugs', TextType::class, [
                'label' => 'label.block_serie_slugs',
                'help' => 'label.block_serie_slugs_help',
                'required' => false,
            ])
            // How each serie is drawn. The stored empty value is the card the listing has always shown, so a block saved before this field existed renders exactly as it did
            ->add('variant', ChoiceType::class, [
                'label' => 'label.block_series_variant',
                'help' => 'label.block_series_variant_help',
                'required' => false,
                'choices' => [
                    'label.block_series_variant_cards' => '',
                    'label.block_series_variant_tiles' => 'tiles',
                    'label.block_series_variant_thumbnails' => 'thumbnails',
                ],
                // No placeholder: the stored empty value is the card variant itself, not the absence of a choice
                'placeholder' => false,
            ])
        ;

        $this->addSelectionFields($builder, 'label.block_max_series');
        $this->addDisplayMoreField($builder, 'label.block_display_more_series');
    }
}
