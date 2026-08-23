<?php

namespace c975L\BookBundle\Form;

use c975L\BookBundle\Entity\BookMedia;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Vich\UploaderBundle\Form\Type\VichImageType;

// The row of the three fields a book's own images are uploaded on - its first cover, its fourth and the backdrop of its page (see BookCrudController). Deliberately not BookMediaType: which of the three a file is, is what the field it was dropped on says (see Book::addCover() and its two siblings), where that form asks for a kind the site had to name itself. VichImageType directly, as SerieMediaType does: these rows are only ever images
class BookCoverType extends AbstractType
{
    use MediaFileFieldTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addIdField($builder);

        $builder
            ->add('position', HiddenType::class, [
                'attr' => ['class' => 'ui-sort-position'],
            ])
            ->add('file', VichImageType::class, [
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
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BookMedia::class,
            'translation_domain' => 'book',
        ]);
    }
}
