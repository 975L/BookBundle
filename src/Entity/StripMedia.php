<?php

namespace c975L\BookBundle\Entity;

use c975L\UiBundle\Contract\VichMediaNamableInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
class StripMedia extends Media implements VichMediaNamableInterface
{
    #[Vich\UploadableField(mapping: 'block_media', fileNameProperty: 'name', size: 'size')]
    protected ?File $file = null;

    #[ORM\ManyToOne(targetEntity: Strip::class, inversedBy: 'medias')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Strip $strip = null;

    public function getStrip(): ?Strip
    {
        return $this->strip;
    }

    public function setStrip(?Strip $strip): static
    {
        $this->strip = $strip;

        return $this;
    }

    public function getVichMediaPath(): string
    {
        return self::MEDIA_DIRECTORY . '/strips/' . $this->getKind() . '-' . ($this->strip?->getSlug() ?? 'temp');
    }
}
