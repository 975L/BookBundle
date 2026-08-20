<?php

namespace c975L\BookBundle\Form;

use c975L\BookBundle\Entity\BookLink;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BookLinkType extends AbstractType
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
            // The platform is a plain string on the entity, the vocabulary being the site's own - stored as it is declared, never as a case of an enum the bundle would have to know
            ->add('kind', ChoiceType::class, [
                'label' => 'label.link_kind',
                'choices' => array_flip(array_map(static fn (array $kind) => $kind['label'], $this->customizationRegistry->getLinkKinds())),
            ])
            // The address as the platform hands it over, pasted whole: what it carries beyond the book - an affiliate identifier, a country, a format anchor - is kept that way
            ->add('url', UrlType::class, [
                'label' => 'label.link_url',
                'help' => 'label.link_url-help',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BookLink::class,
            'translation_domain' => 'book',
        ]);
    }
}
