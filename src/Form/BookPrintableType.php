<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Form;

use c975L\BookBundle\Entity\BookPrintable;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use c975L\BookBundle\Service\BookTranslator;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

// A document to print: a rank, a title and the file like every file a book owns, plus the level it is grouped under on a site naming some (see BookCustomizationProviderInterface::getPrintableKinds())
class BookPrintableType extends BookOwnedMediaType
{
    public function __construct(
        private readonly BookCustomizationRegistry $customizationRegistry,
        ?BookTranslator $bookTranslator = null,
    ) {
        parent::__construct($bookTranslator);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        // A language screen offers the title alone: the level is the same in every language
        $kinds = $this->customizationRegistry->getPrintableKinds();
        if (null !== ($options['translation_locale'] ?? null) || [] === $kinds) {
            return;
        }

        $builder->add('kind', ChoiceType::class, [
            'label' => 'label.printable_kind',
            'required' => false,
            'choices' => array_flip($kinds),
        ]);
    }

    protected function dataClass(): string
    {
        return BookPrintable::class;
    }

    protected function fileLabel(): string
    {
        return 'label.printable';
    }
}
