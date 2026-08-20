<?php

namespace c975L\BookBundle\Form;

use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Vich\UploaderBundle\Form\Type\VichFileType;

class BookMediaType extends AbstractType
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
            // VichFileType and not VichImageType: a version's files are its pages, but also its recording, its trailer and its flipbook - the kind says which, and is left out entirely by a site naming none (see BookCustomizationProviderInterface::getMediaKinds()). Which version the file belongs to is not asked: the form is only ever shown inside the version that holds it (see BookEditionCrudController)
            ->add('file', VichFileType::class, [
                'label' => 'label.media',
                'required' => false,
                'allow_delete' => true,
                'download_uri' => true,
                'asset_helper' => true,
                'constraints' => [
                    new File(maxSize: '100M'),
                ],
            ])
        ;

        $kinds = $this->customizationRegistry->getMediaKinds();

        if ([] !== $kinds) {
            $builder->add('kind', ChoiceType::class, [
                'label' => 'label.media_kind',
                'required' => false,
                'choices' => array_flip($kinds),
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BookMedia::class,
            'translation_domain' => 'book',
        ]);
    }
}
