<?php

namespace c975L\BookBundle\Entity;

use App\Entity\User;
use DateTimeImmutable;
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Entity\BookVideo;
use c975L\BookBundle\Entity\SerieMedia;
use c975L\BookBundle\Entity\BookPresse;
use c975L\BookBundle\Entity\BookMarketing;
use Doctrine\ORM\Mapping as ORM;
use c975L\BookBundle\Repository\MediaRepository;
use Symfony\Component\HttpFoundation\File\File;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Table(name: 'media')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'owner_type', type: 'string')]
#[ORM\DiscriminatorMap([
    'serie' => SerieMedia::class,
    'book' => BookMedia::class,
    'book_marketing' => BookMarketing::class,
    'book_presse' => BookPresse::class,
    'book_video' => BookVideo::class,
])]

abstract class Media
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $position = null;

    #[ORM\Column(length: 100, nullable: true, unique: true)]
    private ?string $name = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(nullable: true)]
    private ?int $size = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $youtubeUrl = null;

    protected ?File $file = null;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne()]
    private ?User $user = null;

    public function __toString(): string
    {
        return (string) $this->getName();
    }

    // Critical for preventing duplicates - overrides default Doctrine behavior
    public function equals(object $other): bool
    {
        if (!$other instanceof Media) {
            return false;
        }

        // If both entities have IDs, compare by ID
        if ($this->getId() !== null && $other->getId() !== null) {
            return $this->getId() === $other->getId();
        }

        // If one doesn't have an ID but both have names, compare by name
        if ($this->getName() && $other->getName()) {
            return $this->getName() === $other->getName();
        }

        // Otherwise, they're not equal
        return false;
    }

    abstract public function getMappingName(): string;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): static
    {
        $this->file = $file;

        if ($file) {
            $this->updatedAt = new DateTimeImmutable();
        }

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getYoutubeUrl(): ?string
    {
        return $this->youtubeUrl;
    }

    public function setYoutubeUrl(?string $youtubeUrl): static
    {
        $this->youtubeUrl = $youtubeUrl;

        if (!empty($youtubeUrl)) {
            $this->setUpdatedAt(new DateTimeImmutable());
            $this->setName('YouTube (' . $youtubeUrl . ')');
        }

        return $this;
    }
}
