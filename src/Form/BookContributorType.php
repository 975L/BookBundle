<?php

namespace c975L\BookBundle\Form;

use c975L\BookBundle\Entity\BookContributor;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Repository\ContributorRepository;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// One credit written on the book's screen: who, and what part they took. Author and illustrator are not offered here - each has a field of its own above, being the two a book takes from its serie (see BookCrudController)
class BookContributorType extends AbstractType
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
            // Only the people the site still records: someone in the trash would be credited on a book, and the foreign key would then refuse to remove them for good (see ContributorRepository::findOneByName())
            ->add('contributor', EntityType::class, [
                'label' => 'label.book_contributor',
                'class' => Contributor::class,
                'query_builder' => static fn (ContributorRepository $repository) => $repository
                    ->createQueryBuilder('c')
                    ->andWhere('c.isDeleted = false')
                    ->orderBy('c.name', 'ASC'),
            ])
            // The parts this site credits, its vocabulary being its own (see BookCustomizationProviderInterface::getContributorRoles())
            ->add('role', ChoiceType::class, [
                'label' => 'label.role',
                'choices' => array_flip($this->customizationRegistry->getContributorRoles()),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BookContributor::class,
            'translation_domain' => 'book',
        ]);
    }
}
