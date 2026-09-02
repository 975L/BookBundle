<?php

namespace c975L\BookBundle\Entity;

use c975L\BookBundle\Contract\TrashableInterface;
use c975L\BookBundle\Entity\Trait\HideableTrait;
use c975L\BookBundle\Entity\Trait\TrashableTrait;
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
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StripRepository::class)]
#[ORM\Table(name: 'book_strip')]
#[UniqueEntity('slug')]
class Strip implements HasBlocksInterface, TrashableInterface, \Stringable
{
    use HasBlocksTrait;
    use HideableTrait;
    use TrashableTrait;

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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $characters = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $charactersSlug = null;

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
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $blocks;

    #[Assert\Valid]
    #[ORM\OneToMany(targetEntity: StripMedia::class, mappedBy: 'strip', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $medias;

    public function __construct()
    {
        $this->blocks = new ArrayCollection();
        $this->medias = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->title;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
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

    public function getCharacters(): ?string
    {
        return $this->characters;
    }

    public function setCharacters(?string $characters): static
    {
        $this->characters = $characters;
        $this->charactersSlug = null === $characters ? null : implode(',', array_map(
            static fn (array $character): string => $character['slug'],
            $this->getCharactersList()
        ));

        return $this;
    }

    public function getCharactersSlug(): ?string
    {
        return $this->charactersSlug;
    }

    /**
     * The characters as pairs of displayed name and url slug, for the links to the character's strips.
     *
     * @return array<int, array{name: string, slug: string}>
     */
    public function getCharactersList(): array
    {
        return self::splitCharacters($this->characters);
    }

    /**
     * The names held by one comma-separated field, each with the slug its own page is reached by - static so a listing can name the characters of a whole serie without loading every planche of it (see StripRepository::findCharactersBySerie()).
     *
     * @return array<int, array{name: string, slug: string}>
     */
    public static function splitCharacters(?string $characters): array
    {
        if (null === $characters) {
            return [];
        }

        $slugger = new AsciiSlugger();
        $list = [];

        foreach (explode(',', $characters) as $character) {
            $character = trim($character);

            if ('' !== $character) {
                $list[] = ['name' => $character, 'slug' => $slugger->slug($character)->lower()->toString()];
            }
        }

        return $list;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
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
}
