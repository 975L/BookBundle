<?php

namespace c975L\BookBundle\Entity;

use c975L\UiBundle\Contract\VichMediaNamableInterface;
use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
class SerieMedia extends Media implements VichMediaNamableInterface
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
