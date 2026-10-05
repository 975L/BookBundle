<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Form;

use c975L\BookBundle\Enum\BookSubjectScheme;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// The codes a category is filed under, one field per trade classification (see BookCategory::$codes) - a scheme added to the enum gets its field without the form being touched
class BookSubjectCodesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (BookSubjectScheme::cases() as $scheme) {
            $builder->add($scheme->value, TextType::class, [
                'label' => $scheme->label(),
                'help' => $scheme->label() . '-help',
                'required' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'book',
        ]);
    }
}
