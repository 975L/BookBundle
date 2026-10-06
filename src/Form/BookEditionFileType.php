<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Form;

use c975L\BookBundle\Entity\BookEditionFile;
use c975L\BookBundle\Enum\BookEditionFileKind;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Vich\UploaderBundle\Form\Type\VichFileType;

// One file an edition is sold as, of the kind the "kind" option names, and the price the shop sells it at - no download link, the file living outside public/ (see BookEditionFile). Left empty, no row is written: the form, not required, hands back null rather than an empty file
class BookEditionFileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var BookEditionFileKind $kind */
        $kind = $options['kind'];

        $builder
            ->add('file', VichFileType::class, [
                'label' => $kind->label(),
                'required' => false,
                'allow_delete' => true,
                'download_uri' => false,
                'constraints' => [
                    new File(maxSize: '500M', extensions: $kind->extensions()),
                ],
            ])
            ->add('price', MoneyType::class, [
                'label' => 'label.edition_file_price',
                'help' => 'label.edition_file_price-help',
                'currency' => false,
                'divisor' => 100,
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BookEditionFile::class,
            'translation_domain' => 'book',
        ]);
        $resolver->setRequired('kind');
        $resolver->setAllowedTypes('kind', BookEditionFileKind::class);
    }
}
