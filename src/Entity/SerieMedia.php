<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Entity;

use c975L\UiBundle\Contract\VichImageResizableInterface;
use c975L\UiBundle\Contract\VichMediaNamableInterface;
use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

// Resized and converted to webp on upload, at the width its kind calls for (see Media::getImageWidth)
#[ORM\Entity]
#[Vich\Uploadable]
class SerieMedia extends Media implements VichImageResizableInterface, VichMediaNamableInterface
{
    #[ORM\ManyToOne(targetEntity: Serie::class, inversedBy: 'medias')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Serie $serie = null;

    public function getSerie(): ?Serie
    {
        return $this->serie;
    }

    public function setSerie(?Serie $serie): static
    {
        $this->serie = $serie;

        return $this;
    }

    public function getVichMediaPath(): string
    {
        return self::MEDIA_DIRECTORY . '/series/' . $this->getKind() . '-' . ($this->serie?->getSlug() ?? 'temp');
    }
}
