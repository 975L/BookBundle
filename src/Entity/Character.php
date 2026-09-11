<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Entity;

use c975L\BookBundle\Repository\CharacterRepository;
use c975L\ConfigBundle\Contract\UserInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

// Who peoples a serie, said once for the whole serie rather than retyped on every planche that character speaks in. Replaces two things that said the same thing apart: the comma-separated names a planche carried, which named a character without being able to draw one, and the cards a site composed by hand to present them - so a face uploaded here is worn by the chip of every planche at once, and the presentation read on the serie's page is the very row the planches point at
// Held by its serie and not by the catalog: two series may people themselves with a "Papa" who is not the same person, so the slug is unique inside a serie and not across the site
#[ORM\Entity(repositoryClass: CharacterRepository::class)]
#[ORM\Table(name: 'book_character')]
#[ORM\UniqueConstraint(name: 'uniq_character_serie_slug', columns: ['serie_id', 'slug'])]
#[UniqueEntity(fields: ['serie', 'slug'], message: 'label.character_slug-unique')]
class Character implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // The order they are presented in, laid by dragging them on their screen - a family is read from its eldest, not from whoever was recorded first
    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(length: 50)]
    private ?string $name = null;

    #[ORM\Column(length: 100)]
    private ?string $slug = null;

    // What the card says of them, in the words the site wrote it - the real name, the character in a sentence, whatever the presentation carried
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $presentation = null;

    // What parts one serie's people into more than one row of cards - "heroes" and "villains" of the same books, told apart on the page without being two series. Left empty, a serie presents its characters as one row, which is what most do
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $groupName = null;

    #[ORM\ManyToOne(targetEntity: Serie::class, inversedBy: 'characters')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Serie $serie = null;

    #[ORM\ManyToOne]
    private ?UserInterface $user = null;

    #[Assert\Valid]
    #[ORM\OneToMany(targetEntity: CharacterMedia::class, mappedBy: 'character', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => \SortDirection::Ascending, 'id' => \SortDirection::Ascending])]
    private Collection $medias;

    #[ORM\ManyToMany(targetEntity: Strip::class, mappedBy: 'characters')]
    private Collection $strips;

    public function __construct()
    {
        $this->medias = new ArrayCollection();
        $this->strips = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }

    // What this row says in the language being rendered, laid over the texts below and stored nowhere on the row: unmapped on purpose, Doctrine computing its changeset from the mapped properties and never from these getters, so a screen rendered in English cannot write English over the text the row was written in (see BookTranslator, the only thing that sets it)
    /** @var array<string, string|null>|null */
    private ?array $translated = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->translated['name'] ?? $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getPresentation(): ?string
    {
        return $this->translated['presentation'] ?? $this->presentation;
    }

    public function setPresentation(?string $presentation): static
    {
        $this->presentation = $presentation;

        return $this;
    }

    public function getGroupName(): ?string
    {
        return $this->groupName;
    }

    public function setGroupName(?string $groupName): static
    {
        $this->groupName = $groupName;

        return $this;
    }

    public function getSerie(): ?Serie
    {
        return $this->serie;
    }

    public function setSerie(?Serie $serie): static
    {
        $this->serie = $serie;

        return $this;
    }

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setUser(?UserInterface $user): static
    {
        $this->user = $user;

        return $this;
    }

    /** @return Collection<int, CharacterMedia> */
    public function getMedias(): Collection
    {
        return $this->medias;
    }

    // The face the chips and the cards wear, or null while none is uploaded - a character then reads as their name alone, which is what every serie showed before any face existed
    public function getMedia(): ?CharacterMedia
    {
        foreach ($this->medias as $media) {
            if (null !== $media->getName()) {
                return $media;
            }
        }

        return null;
    }

    public function addMedia(CharacterMedia $media): static
    {
        if (!$this->medias->contains($media)) {
            $this->medias->add($media);
            $media->setCharacter($this);
        }

        return $this;
    }

    public function removeMedia(CharacterMedia $media): static
    {
        if ($this->medias->removeElement($media) && $media->getCharacter() === $this) {
            $media->setCharacter(null);
        }

        return $this;
    }

    /** @return Collection<int, Strip> */
    public function getStrips(): Collection
    {
        return $this->strips;
    }

    // Lays what a language says over the texts this row was written with, for the render being built and no longer than that - only BookTranslator calls it, and only on the front, a form screen having to go on reading the row
    /** @param array<string, string|null> $values field => value */
    public function setTranslated(array $values): void
    {
        $this->translated = $values;
    }

    // The text the row itself carries, whatever language is being rendered - what a language screen offers as the thing to translate, and what tells an untouched field from a written one (see BookTranslator)
    public function getUntranslated(string $field): ?string
    {
        return match ($field) {
            'name' => $this->name,
            'presentation' => $this->presentation,
            default => null,
        };
    }
}
