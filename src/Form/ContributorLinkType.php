<?php

namespace c975L\BookBundle\Form;

use c975L\BookBundle\Entity\ContributorLink;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// The same row a book's platform is written on (see BookLinkType), read from the same vocabulary: what changes is the entity it writes, a person's link belonging to no book
class ContributorLinkType extends AbstractType
{
    public function __construct(private readonly BookCustomizationRegistry $customizationRegistry)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('position', HiddenType::class, [
                'attr' => ['class' => 'ui-sort-position'],
            ])
            ->add('kind', ChoiceType::class, [
                'label' => 'label.link_kind',
                'choices' => array_flip(array_map(static fn (array $kind) => $kind['label'], $this->customizationRegistry->getLinkKinds())),
            ])
            // The address as the platform hands it over, pasted whole: their author page, not one book's
            ->add('url', UrlType::class, [
                'label' => 'label.link_url',
                'help' => 'label.contributor_link_url-help',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContributorLink::class,
            'translation_domain' => 'book',
        ]);
    }
}
