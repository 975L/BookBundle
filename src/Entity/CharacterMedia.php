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
class CharacterMedia extends Media implements VichMediaNamableInterface
{
    #[ORM\ManyToOne(targetEntity: Character::class, inversedBy: 'medias')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Character $character = null;

    public function getCharacter(): ?Character
    {
        return $this->character;
    }

    public function setCharacter(?Character $character): static
    {
        $this->character = $character;

        return $this;
    }

    // Named after the character it draws, under the serie that peoples itself with them: two series may each carry a "Papa", and a path built on the name alone would have the second overwrite the first
    public function getVichMediaPath(): string
    {
        return self::MEDIA_DIRECTORY . '/characters/' . ($this->character?->getSerie()?->getSlug() ?? 'temp') . '-' . ($this->character?->getSlug() ?? 'temp');
    }
}
