<?php

namespace c975L\BookBundle\Form;

use c975L\BookBundle\Entity\Media;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\File;
use Vich\UploaderBundle\Form\Type\VichFileType;
use Vich\UploaderBundle\Form\Type\VichImageType;

// The file field of the four collections whose rows hold anything - an image, a PDF, a recording, a video (BookMediaType, BookMarketingType, BookPresseType, BookVideoType). An image is asked for as VichImageType so the row shows the thumbnail EasyAdmin's widget renders for it, and so UiBundle's media-preview.js finds the ".ea-vich-image" wrapper it hangs the preview of a freshly picked file on; anything else stays a plain file field. Serie and Strip name VichImageType directly, their rows only ever being images
trait MediaFileFieldTrait
{
    // The row's id, hidden and unmapped: this is what UiBundle's ea-sortable.js reads ([name$="[id]"]) to post the move of a row from one collection to another. Without it the drag lands and the drop puts the row back, nothing saying what to move
    private function addIdField(FormBuilderInterface $builder): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (PreSetDataEvent $event): void {
            $media = $event->getData();

            // A row just added holds no id yet: it stays out of the mechanism until it is saved
            if (!$media instanceof Media || null === $media->getId()) {
                return;
            }

            $event->getForm()->add('id', HiddenType::class, [
                'mapped' => false,
                'data' => (string) $media->getId(),
            ]);
        });
    }

    private function addFileField(FormBuilderInterface $builder, string $label): void
    {
        // Placeholder type, replaced below once the row's own file is known - added here first so "file" keeps the place it is declared in (re-adding a field under the same name replaces it where it stands rather than moving it to the end)
        $builder->add('file', VichFileType::class, $this->fileOptions($label));

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (PreSetDataEvent $event) use ($label): void {
            $media = $event->getData();

            // A row just added holds no file yet, so nothing says it will be an image: it is served the plain file field, and shows its thumbnail once saved
            $isImage = $media instanceof Media && str_starts_with($media->getMimeType(), 'image/');

            $event->getForm()->add('file', $isImage ? VichImageType::class : VichFileType::class, $this->fileOptions($label));
        });
    }

    /** @return array<string, mixed> */
    private function fileOptions(string $label): array
    {
        return [
            'label' => $label,
            'required' => false,
            'allow_delete' => true,
            'download_uri' => true,
            'asset_helper' => true,
            'constraints' => [
                new File(maxSize: '100M'),
            ],
        ];
    }
}
