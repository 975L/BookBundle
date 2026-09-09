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
use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
class ContributorMedia extends Media implements VichMediaNamableInterface
{
    #[ORM\ManyToOne(targetEntity: Contributor::class, inversedBy: 'medias')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Contributor $contributor = null;

    public function getContributor(): ?Contributor
    {
        return $this->contributor;
    }

    public function setContributor(?Contributor $contributor): static
    {
        $this->contributor = $contributor;

        return $this;
    }

    public function getVichMediaPath(): string
    {
        return self::MEDIA_DIRECTORY . '/contributors/' . $this->getKind() . '-' . ($this->contributor?->getSlug() ?? 'temp');
    }
}
