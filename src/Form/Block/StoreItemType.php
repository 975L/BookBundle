<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Form\Block;

use c975L\BookBundle\Service\BookCustomizationRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

// One entry of StoresBlockType's "items" collection: a platform of the site's own vocabulary, and the address it is reached at
class StoreItemType extends AbstractType
{
    public function __construct(
        private readonly BookCustomizationRegistry $customizationRegistry,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Read off the registry and not off BookLinkKind: a site declaring platforms of its own (see BookCustomizationProviderInterface::getLinkKinds()) offers them here too, where the enum alone would ignore them
            ->add('kind', ChoiceType::class, [
                'label' => 'label.block_store_kind',
                'choices' => $this->choices(),
                'constraints' => [new NotBlank()],
            ])
            // The publisher's own page on that platform, which is no book's address: the rail stands on the site's shelf there, where a book's own links point at that book (see templates/components/Book/Shops.html.twig)
            ->add('url', UrlType::class, [
                'label' => 'label.block_store_url',
                'help' => 'label.block_store_url_help',
                'constraints' => [new NotBlank()],
            ])
        ;
    }

    // The platforms the vocabulary holds, by the brand each of them is printed under
    /** @return array<string, string> */
    private function choices(): array
    {
        $choices = [];

        foreach ($this->customizationRegistry->getLinkKinds() as $kind => $platform) {
            $choices[$platform['label']] = $kind;
        }

        return $choices;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'book',
        ]);
    }
}
