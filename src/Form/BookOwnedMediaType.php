<?php

namespace c975L\BookBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// The three collections of files a book holds - videos, presse, marketing - all ask for the same thing: a rank, a title, the file. Only the entity behind the rows, the word naming the file and whether an address can stand in for it change, so BookVideoType, BookPresseType and BookMarketingType declare just that
abstract class BookOwnedMediaType extends AbstractType
{
    use MediaFileFieldTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
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
        ]);
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
                'attr' => [
                    'placeholder' => 'https://www.youtube.com/watch?v=xxx',
                ],
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
