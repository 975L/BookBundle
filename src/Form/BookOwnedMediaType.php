<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Form;

use c975L\BookBundle\Entity\Media;
use c975L\BookBundle\Service\BookTranslator;
use c975L\UiBundle\Form\Util\CollectionReconciler;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

// The three collections of files a book holds - videos, presse, marketing - all ask for the same thing: a rank, a title, the file. Only the entity behind the rows, the word naming the file and whether an address can stand in for it change, so BookVideoType, BookPresseType and BookMarketingType declare just that
abstract class BookOwnedMediaType extends AbstractType
{
    use MediaFileFieldTrait;

    // Null only where a type is built by hand, a test reading its fields: the container always hands it over
    public function __construct(
        private readonly ?BookTranslator $bookTranslator = null,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // A language screen offers the one text a file carries, its title: the file, its rank and its address are the same in every language
        $locale = $options['translation_locale'] ?? null;
        if (null !== $locale) {
            $this->buildTranslation($builder, $locale);

            return;
        }

        $builder
            ->add('position', HiddenType::class, [
                'attr' => ['class' => 'ui-sort-position'],
            ])
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'required' => false,
            ])
        ;

        // Between the title and the file, a row being one or the other: the address of a video hosted as well as uploaded
        if ($this->hasHostedVideo()) {
            $this->addHostedVideoFields($builder);
        }

        $this->addFileField($builder, $this->fileLabel());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => $this->dataClass(),
            'translation_domain' => 'book',
            // A language code opens the row's language screen: its title alone, unmapped (see buildTranslation())
            'translation_locale' => null,
        ]);
        $resolver->setAllowedTypes('translation_locale', ['null', 'string']);
    }

    // The title alone, unmapped so what is written lands in the translation table rather than over the title the file was given - staged and never stored, the root form not being validated yet (see ContentTranslator::stage)
    private function buildTranslation(FormBuilderInterface $builder, string $locale): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (PreSetDataEvent $event) use ($locale): void {
            $media = $event->getData();

            // Matched back by id, the way the composing screen matches its rows
            CollectionReconciler::addIdField($event->getForm(), $media instanceof Media ? $media->getId() : null);

            if ($media instanceof Media && null !== $this->bookTranslator) {
                $event->getForm()->add('title', TextType::class, [
                    'label' => 'label.title',
                    'required' => false,
                    'mapped' => false,
                    'data' => $this->bookTranslator->promptValues($media, $locale)['title'] ?? null,
                ]);
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (PostSubmitEvent $event) use ($locale): void {
            $media = $event->getData();
            if ($media instanceof Media && $event->getForm()->has('title')) {
                $this->bookTranslator?->stage($media, $locale, ['title' => $event->getForm()->get('title')->getData()]);
            }
        });
    }

    // The entity the rows of the collection are
    /** @return class-string */
    abstract protected function dataClass(): string;

    // The word naming the file field, the collection itself being named by the fieldset it sits in
    abstract protected function fileLabel(): string;

    // Whether a row can name a hosted video instead of holding a file, which is what makes the player printable
    protected function hasHostedVideo(): bool
    {
        return false;
    }

    // The whole address and no longer the id alone, under UiBundle's words which already translated them for its own video blocks: it is the same thing that is pasted, whatever the form
    private function addHostedVideoFields(FormBuilderInterface $builder): void
    {
        $builder
            ->add('youtubeUrl', UrlType::class, [
                'label' => 'label.video_url',
                'required' => false,
                'translation_domain' => 'ui',
            ])
            // Ticked by default (see Media::$noCookie): the rewriting happens once, on save, and the template never has to care (see BookVideoNoCookieListener)
            ->add('noCookie', CheckboxType::class, [
                'label' => 'label.video_no_cookie',
                'required' => false,
                'translation_domain' => 'ui',
            ])
        ;
    }
}
