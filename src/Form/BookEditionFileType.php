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
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Image;
use Vich\UploaderBundle\Form\Type\VichFileType;

// One file an edition holds, of the kind the "kind" option names, and the price the shop sells it at - no download link, the file living outside public/ (see BookEditionFile). Left empty, no row is written: the form, not required, hands back null rather than an empty file
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
                'help' => BookEditionFileKind::CoverFront === $kind ? 'label.edition_file_cover_front-help' : null,
                'constraints' => [
                    new File(maxSize: '500M', extensions: $kind->extensions()),
                    // Apple takes no front cover under 1024 pixels on its shorter side
                    ...(BookEditionFileKind::CoverFront === $kind ? [new Image(minWidth: 1024, minHeight: 1024)] : []),
                ],
            ])
        ;

        // A cover is not sold, it has no price
        if ($kind->isSold()) {
            $builder->add('price', MoneyType::class, [
                'label' => 'label.edition_file_price',
                'help' => 'label.edition_file_price-help',
                'currency' => false,
                'divisor' => 100,
                'required' => false,
            ]);
        }

        // Only an EPUB can be read aloud along its text
        if (BookEditionFileKind::Epub === $kind) {
            $builder->add('readAloud', CheckboxType::class, [
                'label' => 'label.edition_file_read_aloud',
                'help' => 'label.edition_file_read_aloud-help',
                'required' => false,
            ]);
        }
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
