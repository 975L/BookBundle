<?php

namespace c975L\BookBundle\Form;

use c975L\BookBundle\Entity\BookLink;
use c975L\BookBundle\Enum\BookLinkKind;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BookLinkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('position', HiddenType::class, [
                'attr' => ['class' => 'ui-sort-position'],
            ])
            ->add('kind', EnumType::class, [
                'class' => BookLinkKind::class,
                'label' => 'label.link_kind',
                'choice_label' => static fn (BookLinkKind $kind) => $kind->label(),
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
