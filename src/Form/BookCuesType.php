<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Form;

use c975L\BookBundle\Entity\BookMedia;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Vich\UploaderBundle\Form\Type\VichFileType;

// The timecodes row: a WebVTT file telling when each page starts in the recording (see BookMediaKind::Cues), checked on its extension - a .vtt is plain text to whatever sniffs its content
class BookCuesType extends AbstractType
{
    use MediaFileFieldTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addIdField($builder);

        $builder
            ->add('position', HiddenType::class, [
                'attr' => ['class' => 'ui-sort-position'],
            ])
            ->add('file', VichFileType::class, [
                'label' => 'label.media',
                'required' => false,
                'allow_delete' => true,
                'download_uri' => true,
                'asset_helper' => true,
                'constraints' => [
                    new File(maxSize: '1M', extensions: ['vtt' => ['text/vtt', 'text/plain']], extensionsMessage: 'label.cues-help'),
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
