<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Form;

use c975L\BookBundle\Entity\BookEdition;
use c975L\BookBundle\Entity\BookEditionFile;
use c975L\BookBundle\Enum\BookChannel;
use c975L\BookBundle\Enum\BookEditionFileKind;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

// An edition a book comes out under - paper, digital, audio - edited where it reads, in the accordion the book's form unfolds. Nothing but what belongs to it: its ISBN, its size, its pagination, its price, the files it is sold as and where it is handed out. The other files and the platforms belong to the book and are edited under the gesture they serve - the recording under "Listen" with the podcast apps, the bookshops under "Buy" (see BookCrudController)
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
            // What the stores are told it sells for, tax included (see BookOnixBuilder) - in cents like ShopBundle's, the currency beside it
            ->add('price', MoneyType::class, [
                'label' => 'label.edition_price',
                'help' => 'label.edition_price-help',
                'currency' => false,
                'divisor' => 100,
                'required' => false,
            ])
            ->add('currency', CurrencyType::class, [
                'label' => 'label.edition_currency',
                'preferred_choices' => ['EUR'],
            ])
            // Where the edition is handed out - the shop, the public ONIX, Google... (see BookChannel)
            ->add('channels', ChoiceType::class, [
                'label' => 'label.edition_channels',
                'help' => 'label.edition_channels-help',
                'choices' => BookChannel::choices(),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
        ;

        // The files it is sold as, one slot per kind the edition can be sold as (see BookEditionFileKind::forEdition()), read and written through the edition's own accessors: a slot left empty or emptied with its checkbox takes the kind's file away (see BookEdition::setFileOf()). Edited by reference, a clone of the stored file being a new row to Doctrine, inserted over the one it copies
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
            $edition = $event->getData();
            foreach (BookEditionFileKind::forEdition($edition instanceof BookEdition ? $edition->getKind() : null) as $kind) {
                $event->getForm()->add('file_' . $kind->value, BookEditionFileType::class, [
                    'label' => false,
                    'required' => false,
                    'kind' => $kind,
                    'getter' => static fn (BookEdition $edition): ?BookEditionFile => $edition->getFileOf($kind),
                    'setter' => static function (BookEdition $edition, ?BookEditionFile $file) use ($kind): void {
                        $edition->setFileOf($kind, $file);
                    },
                ]);
            }
        });

        // The same file coming back skips the setter, so a slot emptied with its checkbox reaches setFileOf() here, once the upload has been deleted
        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $edition = $event->getData();
            if (!$edition instanceof BookEdition) {
                return;
            }

            foreach (BookEditionFileKind::cases() as $kind) {
                if ($event->getForm()->has('file_' . $kind->value)) {
                    $edition->setFileOf($kind, $event->getForm()->get('file_' . $kind->value)->getData());
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BookEdition::class,
            'translation_domain' => 'book',
        ]);
    }
}
