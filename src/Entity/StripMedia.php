<?php

namespace c975L\BookBundle\Entity;

use c975L\UiBundle\Contract\VichMediaNamableInterface;
use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
class StripMedia extends Media implements VichMediaNamableInterface
{
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

    // The kind only counts where a planche tells several apart: without it the path would open on a dash, which the file would then carry for good
    public function getVichMediaPath(): string
    {
        $kind = (string) $this->getKind();

        return self::MEDIA_DIRECTORY . '/strips/' . ('' !== $kind ? $kind . '-' : '') . ($this->strip?->getSlug() ?? 'temp');
    }
}
