<?php

namespace c975L\BookBundle\Entity;

use c975L\BookBundle\Repository\MediaRepository;
use c975L\ConfigBundle\Contract\UserInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Table(name: 'book_media')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'owner_type', type: 'string')]
#[ORM\DiscriminatorMap([
    'serie' => SerieMedia::class,
    'book' => BookMedia::class,
    'book_marketing' => BookMarketing::class,
    'book_presse' => BookPresse::class,
    'book_video' => BookVideo::class,
    'strip' => StripMedia::class,
])]
abstract class Media implements \Stringable
{
    // The root every subclass writes under (see their getVichMediaPath), and what BookBackupPathProvider declares to ConfigBundle
    public const string MEDIA_DIRECTORY = 'medias/book';

    // UiBundle's Slider and Image components read a media through the same accessors as UiBundle's own Media entity - the nine below fill that contract without adding a column, the display metadata they stand for having no home in this hierarchy
    private const array MIME_TYPES = [
        'avif' => 'image/avif',
        'epub' => 'application/epub+zip',
        'gif' => 'image/gif',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'mp3' => 'audio/mpeg',
        'mp4' => 'video/mp4',
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'svg' => 'image/svg+xml',
        'webm' => 'video/webm',
        'webp' => 'image/webp',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $position = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $kind = null;

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
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne()]
    private ?UserInterface $user = null;

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
        if (null !== $this->getId() && null !== $other->getId()) {
            return $this->getId() === $other->getId();
        }

        // If one doesn't have an ID but both have names, compare by name
        if ($this->getName() && $other->getName()) {
            return $this->getName() === $other->getName();
        }

        // Otherwise, they're not equal
        return false;
    }

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
        $this->position = $position ?? 0;

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

    public function getKind(): ?string
    {
        return $this->kind;
    }

    public function setKind(?string $kind): static
    {
        $this->kind = $kind;

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
            $this->updatedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

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

    // UiBundle's Slider and Image components read a media through the same accessors as UiBundle's own Media entity - the nine below fill that contract without adding a column, the display metadata they stand for having no home in this hierarchy. This one is read off the stored name, the Slider only asking to tell a video from an image
    public function getMimeType(): string
    {
        $extension = strtolower(pathinfo((string) $this->name, PATHINFO_EXTENSION));

        return self::MIME_TYPES[$extension] ?? 'application/octet-stream';
    }

    // The title typed in the back-office is the only text this hierarchy holds, so it stands as the alternative text too
    public function getAlt(): ?string
    {
        return $this->title;
    }

    public function getLabel(): ?string
    {
        return null;
    }

    public function getWidth(): ?string
    {
        return null;
    }

    public function getHeight(): ?string
    {
        return null;
    }

    /** @return list<string> */
    public function getCssClasses(): array
    {
        return [];
    }

    public function isAbove(): bool
    {
        return false;
    }

    public function getCredits(): ?string
    {
        return null;
    }

    public function isRightsReserved(): bool
    {
        return false;
    }

    public function getYoutubeUrl(): ?string
    {
        return $this->youtubeUrl;
    }

    public function setYoutubeUrl(?string $youtubeUrl): static
    {
        $this->youtubeUrl = $youtubeUrl;

        if (!empty($youtubeUrl)) {
            $this->setUpdatedAt(new \DateTimeImmutable());
            $this->setName('YouTube (' . $youtubeUrl . ')');
        }

        return $this;
    }
}
