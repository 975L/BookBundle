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
use Symfony\Component\Validator\Constraints\NotBlank;

// The data sub-form of the "book_serie_strips" kind - the head it shares with every other listing is in AbstractBookListingBlockType
class SerieStripsBlockType extends AbstractBookListingBlockType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Added before the head this kind shares with the other listings: it is the only required field of the form, and it would otherwise be read after six optional ones
        $builder
            ->add('serieSlug', TextType::class, [
                'label' => 'label.block_serie_slug',
                'required' => true,
                'constraints' => [new NotBlank()],
            ])
        ;

        parent::buildForm($builder, $options);

        $this->addSelectionFields($builder, 'label.block_max_strips');
    }
}
