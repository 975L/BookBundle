<?php

namespace c975L\BookBundle\Form;

use c975L\BookBundle\Entity\BookPresse;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Vich\UploaderBundle\Form\Type\VichFileType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

class BookPresseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('position', IntegerType::class, [
                'required' => false,
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'required' => false,
            ])
            ->add('youtubeUrl', null, [
                'label' => 'YouTube Id',
                'help' => 'SOIT un identifiant Youtube, SOIT un fichier vidéo ci-dessous',
                'required' => false,
                'attr' => [
                    'placeholder' => 'xxx',
                ],
            ])
            ->add('file', VichFileType::class, [
                'label' => 'Presse',
                'required' => false,
                'allow_delete' => true,
                'download_uri' => true,
                'asset_helper' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '100M',
                    ])
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BookPresse::class,
        ]);
    }
}
