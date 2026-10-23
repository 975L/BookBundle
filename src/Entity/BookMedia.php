<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Entity;

use c975L\UiBundle\Contract\VichMediaNamableInterface;
use c975L\UiBundle\Contract\VichMultiSizeImageInterface;
use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

// Resized and converted to webp on upload, at the width its kind calls for (see Media::getImageWidth), with a "-highres.webp" beside it that the pages' zoom and the apps' tablets open (see UiBundle's VichImageResizeListener)
#[ORM\Entity]
#[Vich\Uploadable]
class BookMedia extends Media implements VichMultiSizeImageInterface, VichMediaNamableInterface
{
    #[ORM\ManyToOne(targetEntity: Book::class, inversedBy: 'medias')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Book $book = null;

    public function getBook(): ?Book
    {
        return $this->book;
    }

    public function setBook(?Book $book): static
    {
        $this->book = $book;

        return $this;
    }

    // No book screen shows its images as a grid, so UiBundle cuts no thumbnail for them
    public function getThumbnailSize(): int
    {
        return 0;
    }

    // Wide enough for a tablet's full screen and the zoom, capped by UiBundle at the upload's own width
    public function getHighresWidth(): int
    {
        return 1600;
    }

    public function getVichMediaPath(): string
    {
        return self::MEDIA_DIRECTORY . '/books/' . $this->getKind() . '-' . ($this->book?->getSlug() ?? 'temp');
    }
}
