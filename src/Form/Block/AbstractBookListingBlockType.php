<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Form\Block;

use c975L\UiBundle\Form\Block\HasAnchorFieldTrait;
use c975L\UiBundle\Form\Block\HasBackgroundFieldTrait;
use c975L\UiBundle\Form\TrixEditorType;
use c975L\UiBundle\Service\BlockAnchorSlugger;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// The head every listing kind of this bundle carries - anchor, eyebrow, title, paragraph and colored flat, the very fields a "text_section" offers. Written here rather than left to a text block placed above each listing: the two were one section on the page and two rows in the back office, and the pair had to be moved, hidden and translated together (see templates/components/Listing/Section.html.twig)
abstract class AbstractBookListingBlockType extends AbstractType
{
    use HasAnchorFieldTrait;
    use HasBackgroundFieldTrait;

    public function __construct(
        private readonly BlockAnchorSlugger $anchorSlugger,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addAnchorField($builder, $this->anchorSlugger);

        $builder
            ->add('eyebrow', TextType::class, [
                'label' => 'label.block_eyebrow',
                'required' => false,
            ])
            // Optional, like the eyebrow above it: a listing typed with neither renders as a bare grid of covers, which is what every page composed before these fields existed shows
            ->add('title', TextType::class, [
                'label' => 'label.block_title',
                'required' => false,
            ])
            ->add('content', TrixEditorType::class, [
                'label' => 'label.block_content',
                'required' => false,
            ])
        ;

        $this->addBackgroundField($builder);
    }

    // The rows a listing draws, and how it picks them - added by the kinds having a whole catalog to cut into, not by the one pointing at a single serie
    protected function addSelectionFields(FormBuilderInterface $builder, string $maxLabel): void
    {
        $builder
            ->add('max', IntegerType::class, [
                'label' => $maxLabel,
                'required' => false,
                'attr' => ['min' => 1],
            ])
            // Only meaningful together with a maximum: it decides which rows of the catalog that maximum keeps, the block then declining its own cache entry so the draw is renewed at every render (see BookBlockCacheTagProvider)
            ->add('random', CheckboxType::class, [
                'label' => 'label.block_random',
                'help' => 'label.block_random_help',
                'required' => false,
            ])
        ;
    }

    // The card closing the grid, the one leading to the whole catalog - only for the listings a page of their own answers to
    protected function addDisplayMoreField(FormBuilderInterface $builder, string $label): void
    {
        $builder->add('displayMore', CheckboxType::class, [
            'label' => $label,
            'required' => false,
        ]);
    }

    // BlockType translates the embedded data form in the "ui" domain: without this, every label above would be looked up there and rendered raw
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'book',
        ]);
    }
}
