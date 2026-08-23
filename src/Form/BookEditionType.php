<?php

namespace c975L\BookBundle\Form;

use c975L\BookBundle\Entity\BookEdition;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// An edition a book comes out under - paper, digital, audio - edited where it reads, in the accordion the book's form unfolds
// Nothing but what belongs to it: its ISBN, its size, its pagination, its release date. The files and the platforms belong to the book and are edited under the gesture they serve - the recording under "Listen" with the podcast apps, the bookshops under "Buy" (see BookCrudController)
class BookEditionType extends AbstractType
{
    public function __construct(
        private readonly BookCustomizationRegistry $customizationRegistry,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('position', HiddenType::class, [
                'attr' => ['class' => 'ui-sort-position'],
            ])
            // The form of the book the site names, its vocabulary being its own (see BookCustomizationProviderInterface::getEditionKinds())
            ->add('kind', ChoiceType::class, [
                'label' => 'label.edition_kind',
                'choices' => array_flip($this->customizationRegistry->getEditionKinds()),
            ])
            ->add('isbn', TextType::class, [
                'label' => 'label.isbn',
                'required' => false,
            ])
            ->add('pages', IntegerType::class, [
                'label' => 'label.pages',
                'help' => 'label.edition_pages-help',
                'required' => false,
            ])
            // What the version is made of - a paper size, a file type - which the book itself no longer says: a paperback and an ebook of the same story never share it
            ->add('format', TextType::class, [
                'label' => 'label.format',
                'help' => 'label.edition_format-help',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BookEdition::class,
            'translation_domain' => 'book',
        ]);
    }
}
