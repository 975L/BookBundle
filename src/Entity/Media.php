<?php

namespace c975L\BookBundle\Entity;

use c975L\BookBundle\Repository\MediaRepository;
use c975L\ConfigBundle\Contract\UserInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Table(name: 'book_media')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'owner_type', type: 'string')]
#[ORM\DiscriminatorMap([
    'serie' => SerieMedia::class,
    'book' => BookMedia::class,
    'contributor' => ContributorMedia::class,
    'book_marketing' => BookMarketing::class,
    'book_presse' => BookPresse::class,
    'book_video' => BookVideo::class,
    'strip' => StripMedia::class,
])]
abstract class Media implements \Stringable
{
    // What each subclass still repeats - its $book/$serie/$strip and its getVichMediaPath() - stays there on purpose: the inversedBy differs on each, and Doctrine's AssociationOverride, which would redeclare them per subclass, is not supported under SINGLE_TABLE, only under a MappedSuperclass. Raising the relation here would mean dropping the inversedBy and checking at doctrine:schema:validate what the owning collections make of it

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
        // A recording can also come in ogg: without it the "Listen" card did not see the file, its type falling back on "application/octet-stream"
        'oga' => 'audio/ogg',
        'ogg' => 'audio/ogg',
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

    // The player's whole address and no longer the id alone: it is what an editor pastes, and what the rendering hands as is to the iframe (see BookVideoNoCookieListener, which brings it back to its platform's canonical form)
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $youtubeUrl = null;

    // Ticked, the pasted address is rewritten into a privacy-respecting one on save - youtube-nocookie.com for YouTube, "dnt=1" for Vimeo (see c975L\UiBundle\Video\VideoPlatform). Ticked by default: it is the only origin the site's security policy allows, and a "watch?v=" address does not embed
    #[ORM\Column(options: ['default' => true])]
    private bool $noCookie = true;

    // The upload declared once for the whole hierarchy: Vich reads a property off the parent classes too, so each subclass only carries the #[Vich\Uploadable] that marks it uploadable, an attribute that is not inherited
    #[Vich\UploadableField(mapping: 'block_media', fileNameProperty: 'name', size: 'size')]
    protected ?File $file = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne()]
    private ?UserInterface $user = null;

    // A media row is written as soon as its owner is, whether or not a file has been picked - so it carries a date from the moment it exists, an upload simply moving that date forward (see setFile)
    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return (string) $this->getName();
    }

    // A row exists from the moment its owner is saved (see the constructor), so nothing stops one being written with no file at all - and a template asking for the first media of a collection is handed that empty row rather than falling back on its placeholder. Not a NotBlank on $name: Vich fills it from the upload after the validation has run, so a legitimate new file would be turned away. A hosted video stands for its own file, its address being what the player reads (see BookVideo and Media::$youtubeUrl)
    #[Assert\Callback]
    public function validateFileOrAddress(ExecutionContextInterface $context): void
    {
        if (null !== $this->file || null !== $this->name || null !== $this->youtubeUrl) {
            return;
        }

        $context->buildViolation('label.media_without_file')
            ->atPath('file')
            ->addViolation();
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

    public function isNoCookie(): bool
    {
        return $this->noCookie;
    }

    public function setNoCookie(bool $noCookie): static
    {
        $this->noCookie = $noCookie;

        return $this;
    }

    public function getYoutubeUrl(): ?string
    {
        return $this->youtubeUrl;
    }

    public function setYoutubeUrl(?string $youtubeUrl): static
    {
        $this->youtubeUrl = $youtubeUrl;

        // No name is written: it is unique across the whole book_media table, and the same video may legitimately appear on two rows
        if (!empty($youtubeUrl)) {
            $this->setUpdatedAt(new \DateTimeImmutable());
        }

        return $this;
    }
}
