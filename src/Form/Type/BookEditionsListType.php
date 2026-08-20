<?php

namespace c975L\BookBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

// Not bound to any real Book property - renders entirely through its own form theme block (c975l_book_editions_widget in book_crud_form_theme.html.twig), which lists the versions of the book and links into the screen editing each one (see c975L\BookBundle\Controller\Management\BookEditionCrudController). Same "mapped: false" pattern as SiteBundle's PageQrCodeType: a version holds its own files and its own platforms, which a collection row inside the book's form could neither show nor edit
class BookEditionsListType extends AbstractType
{
    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'c975l_book_editions';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'mapped' => false,
            'required' => false,
        ]);
    }
}
