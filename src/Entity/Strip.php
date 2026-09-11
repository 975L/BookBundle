<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Entity;

use c975L\BookBundle\Contract\TrashableInterface;
use c975L\BookBundle\Entity\Trait\HideableTrait;
use c975L\BookBundle\Entity\Trait\TrashableTrait;
use c975L\BookBundle\Enum\StripMediaKind;
use c975L\BookBundle\Repository\StripRepository;
use c975L\ConfigBundle\Contract\UserInterface;
use c975L\UiBundle\Contract\HasBlocksInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Trait\HasBlocksTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StripRepository::class)]
#[ORM\Table(name: 'book_strip')]
#[UniqueEntity('slug')]
class Strip implements HasBlocksInterface, TrashableInterface, \Stringable
{
    use HasBlocksTrait;
    use HideableTrait;
    use TrashableTrait;

    // The roles the back-office gives a field of their own (see getPageMedias() and getCaseMedias()). Everything else falls to getOtherMedias(), which is what keeps a media editable whatever role it carries - so a role with no field of its own, the card and the thumbnail among them, is still reachable and can still be given from there
    private const array OWN_COLLECTION_KINDS = [
        StripMediaKind::Page->value,
        StripMediaKind::Panel->value,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $title = null;

    #[ORM\Column(length: 100)]
    private ?string $slug = null;

    #[ORM\Column(nullable: true)]
    private ?int $number = null;

    // Who speaks in the planche, pointing at the serie's own people rather than naming them again as text - a name could not be misspelled into a second character, and renaming one renames it on every planche at once (see Character)
    #[ORM\ManyToMany(targetEntity: Character::class, inversedBy: 'strips')]
    #[ORM\JoinTable(name: 'book_strip_character')]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $characters;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $published = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $creation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $modification = null;

    #[ORM\ManyToOne(targetEntity: Serie::class, inversedBy: 'strips')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Serie $serie = null;

    #[ORM\ManyToOne]
    private ?UserInterface $user = null;

    #[ORM\ManyToMany(targetEntity: Block::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'book_strip_block')]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $blocks;

    #[Assert\Valid]
    // The id breaks the ties: the position is typed in the back-office, so nothing makes it unique, and on the rows sharing one the database is free to order as it likes - a planche whose panels shared a position was read in one order on a page load and in another on the next
    #[ORM\OneToMany(targetEntity: StripMedia::class, mappedBy: 'strip', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $medias;

    public function __construct()
    {
        $this->blocks = new ArrayCollection();
        $this->characters = new ArrayCollection();
        $this->medias = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->title;
    }

    // What this row says in the language being rendered, laid over the texts below and stored nowhere on the row: unmapped on purpose, Doctrine computing its changeset from the mapped properties and never from these getters, so a screen rendered in English cannot write English over the text the row was written in (see BookTranslator, the only thing that sets it)
    /** @var array<string, string|null>|null */
    private ?array $translated = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->translated['title'] ?? $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

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

    public function getNumber(): ?int
    {
        return $this->number;
    }

    public function setNumber(?int $number): static
    {
        $this->number = $number;

        return $this;
    }

    /** @return Collection<int, Character> */
    public function getCharacters(): Collection
    {
        return $this->characters;
    }

    public function addCharacter(Character $character): static
    {
        if (!$this->characters->contains($character)) {
            $this->characters->add($character);
        }

        return $this;
    }

    public function removeCharacter(Character $character): static
    {
        $this->characters->removeElement($character);

        return $this;
    }

    /**
     * The characters as pairs of displayed name and url slug - the shape the row of chips and the structured data have always read, kept so the relation could replace the text field under them without either being rewritten.
     *
     * @return array<int, array{name: string, slug: string}>
     */
    public function getCharactersList(): array
    {
        $list = [];

        foreach ($this->characters as $character) {
            $list[] = ['name' => (string) $character->getName(), 'slug' => (string) $character->getSlug()];
        }

        return $list;
    }

    public function getSummary(): ?string
    {
        return $this->translated['summary'] ?? $this->summary;
    }

    public function setSummary(?string $summary): static
    {
        $this->summary = $summary;

        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $sourceUrl): static
    {
        $this->sourceUrl = $sourceUrl;

        return $this;
    }

    public function getPublished(): ?\DateTimeInterface
    {
        return $this->published;
    }

    public function setPublished(?\DateTimeInterface $published): static
    {
        $this->published = $published;

        return $this;
    }

    public function getCreation(): ?\DateTimeInterface
    {
        return $this->creation;
    }

    public function setCreation(\DateTimeInterface $creation): static
    {
        $this->creation = $creation;

        return $this;
    }

    public function getModification(): ?\DateTimeInterface
    {
        return $this->modification;
    }

    public function setModification(\DateTimeInterface $modification): static
    {
        $this->modification = $modification;

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

    /** @return Collection<int, StripMedia> */
    public function getMedias(): Collection
    {
        return $this->medias;
    }

    /**
     * The medias playing one role, in the order they are shown - what the page reads to tell the panels from the page they were cut from (see StripMediaKind and Strip:Medias).
     *
     * @return Collection<int, StripMedia>
     */
    public function getMediasByKind(string $kind): Collection
    {
        return $this->medias->filter(
            static fn (StripMedia $media): bool => $kind === $media->getKind()
        );
    }

    // What stands for the planche in a listing, read by role and not by rank: since the roles, the first media of the collection is no longer the one meant to represent it, and a wall of thumbnails would show a panel instead of the planche. A different order from the one the share image goes by (see strip/display.html.twig), which reaches for the whole page first
    public function getThumbnailMedia(): ?StripMedia
    {
        foreach ([StripMediaKind::Thumbnail->value, StripMediaKind::Card->value] as $kind) {
            $media = $this->getMediasByKind($kind)->first();

            if ($media instanceof StripMedia) {
                return $media;
            }
        }

        $media = $this->medias->first();

        return $media instanceof StripMedia ? $media : null;
    }

    // The four views below are what the back-office binds a field to, one per role, the way a book's links are split by group (see Book::getBuyLinks()): getMediasByKind() takes an argument, so a form cannot bind it. Each adder writes the role its own field stands for - an editor picks the collection, never the value - and every one of them delegates to addMedia(), which is what owns the relation

    /** @return Collection<int, StripMedia> */
    public function getPageMedias(): Collection
    {
        return $this->getMediasByKind(StripMediaKind::Page->value);
    }

    public function addPageMedia(StripMedia $media): static
    {
        return $this->addMedia($media->setKind(StripMediaKind::Page->value));
    }

    public function removePageMedia(StripMedia $media): static
    {
        return $this->removeMedia($media);
    }

    /** @return Collection<int, StripMedia> */
    public function getCaseMedias(): Collection
    {
        return $this->getMediasByKind(StripMediaKind::Panel->value);
    }

    public function addCaseMedia(StripMedia $media): static
    {
        return $this->addMedia($media->setKind(StripMediaKind::Panel->value));
    }

    public function removeCaseMedia(StripMedia $media): static
    {
        return $this->removeMedia($media);
    }

    /**
     * What none of the three collections above claims: a planche imported before the roles existed and carrying none, and the roles no field of its own shows - the square card a listing is given and the thumbnail an import writes. Read against the three and not against the enum: a role the back-office does not split out is a valid one all the same, and one left out of here would be editable from nowhere at all.
     *
     * @return Collection<int, StripMedia>
     */
    public function getOtherMedias(): Collection
    {
        return $this->medias->filter(
            static fn (StripMedia $media): bool => !in_array((string) $media->getKind(), self::OWN_COLLECTION_KINDS, true)
        );
    }

    public function addOtherMedia(StripMedia $media): static
    {
        return $this->addMedia($media);
    }

    public function removeOtherMedia(StripMedia $media): static
    {
        return $this->removeMedia($media);
    }

    public function addMedia(StripMedia $media): static
    {
        if (!$this->medias->contains($media)) {
            $this->medias->add($media);
            $media->setStrip($this);
        }

        return $this;
    }

    public function removeMedia(StripMedia $media): static
    {
        if ($this->medias->removeElement($media)) {
            if ($media->getStrip() === $this) {
                $media->setStrip(null);
            }
        }

        return $this;
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
            'title' => $this->title,
            'summary' => $this->summary,
            default => null,
        };
    }
}
