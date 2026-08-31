<?php

namespace c975L\BookBundle\Entity;

use c975L\BookBundle\Contract\TrashableInterface;
use c975L\BookBundle\Entity\Trait\HideableTrait;
use c975L\BookBundle\Entity\Trait\TrashableTrait;
use c975L\BookBundle\Enum\SerieKind;
use c975L\BookBundle\Repository\SerieRepository;
use c975L\ConfigBundle\Contract\UserInterface;
use c975L\UiBundle\Contract\HasBlocksInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Trait\HasBlocksTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: SerieRepository::class)]
#[ORM\Table(name: 'book_serie')]
#[UniqueEntity('slug')]
class Serie implements HasBlocksInterface, TrashableInterface, \Stringable
{
    use HasBlocksTrait;
    use HideableTrait;
    use TrashableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // The order the series show in, which the editor lays by dragging them on their screen (see SerieCrudController::reorder()): without it a catalog read in the order its series were created
    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(length: 50)]
    private ?string $title = null;

    #[ORM\Column(length: 50)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $kind = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $language = null;

    // The two people a serie credits, which every book it holds inherits unless it names its own (see Book::getEffectiveAuthor())
    #[ORM\ManyToOne(targetEntity: Contributor::class, inversedBy: 'authoredSeries')]
    private ?Contributor $author = null;

    #[ORM\ManyToOne(targetEntity: Contributor::class, inversedBy: 'illustratedSeries')]
    private ?Contributor $illustrator = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $creation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $modification = null;

    #[ORM\ManyToMany(targetEntity: Block::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'book_serie_block')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $blocks;

    #[ORM\OneToMany(targetEntity: Book::class, mappedBy: 'serie')]
    #[ORM\OrderBy(['number' => 'ASC', 'published' => 'ASC'])]
    private Collection $books;

    #[ORM\OneToMany(targetEntity: Strip::class, mappedBy: 'serie')]
    #[ORM\OrderBy(['number' => 'ASC', 'published' => 'ASC'])]
    private Collection $strips;

    #[ORM\OneToMany(targetEntity: SerieMedia::class, mappedBy: 'serie', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $medias;

    #[ORM\ManyToOne]
    private ?UserInterface $user = null;

    public function __construct()
    {
        $this->blocks = new ArrayCollection();
        $this->books = new ArrayCollection();
        $this->strips = new ArrayCollection();
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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
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

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): static
    {
        $this->summary = $summary;

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

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function getAuthor(): ?Contributor
    {
        return $this->author;
    }

    public function setAuthor(?Contributor $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getIllustrator(): ?Contributor
    {
        return $this->illustrator;
    }

    public function setIllustrator(?Contributor $illustrator): static
    {
        $this->illustrator = $illustrator;

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

    /**
     * @return Collection<int, Book>
     */
    public function getBooks(): Collection
    {
        return $this->books;
    }

    public function addBook(Book $book): static
    {
        if (!$this->books->contains($book)) {
            $this->books->add($book);
            $book->setSerie($this);
        }

        return $this;
    }

    public function removeBook(Book $book): static
    {
        if ($this->books->removeElement($book)) {
            // set the owning side to null (unless already changed)
            if ($book->getSerie() === $this) {
                $book->setSerie(null);
            }
        }

        return $this;
    }

    /** @return Collection<int, SerieMedia> */
    public function getMedias(): Collection
    {
        return $this->medias;
    }

    public function addMedia(SerieMedia $media): static
    {
        if (!$this->medias->contains($media)) {
            $this->medias->add($media);
            $media->setSerie($this);
        }

        return $this;
    }

    public function removeMedia(SerieMedia $media): static
    {
        if ($this->medias->removeElement($media)) {
            if ($media->getSerie() === $this) {
                $media->setSerie(null);
            }
        }

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

    public function getStrips(): Collection
    {
        return $this->strips;
    }

    // Which of the two indexes this serie answers to, they share none: what it declares, and what it holds when it declares nothing (see SerieKind). The same rule SerieRepository::findWithStrips() writes in DQL, said once here for the pages reading a serie in hand - the breadcrumb above all, which would otherwise send a visitor back to an index this serie is not on
    public function isStripSerie(): bool
    {
        if (null !== $this->kind) {
            return SerieKind::Strip->value === $this->kind;
        }

        return !$this->strips->isEmpty();
    }

    public function addStrip(Strip $strip): static
    {
        if (!$this->strips->contains($strip)) {
            $this->strips->add($strip);
            $strip->setSerie($this);
        }

        return $this;
    }

    public function removeStrip(Strip $strip): static
    {
        if ($this->strips->removeElement($strip)) {
            if ($strip->getSerie() === $this) {
                $strip->setSerie(null);
            }
        }

        return $this;
    }

    // Whether any book or strip still names this serie - what refuses it the trash, a serie being removed once it is empty and not before (see SerieCrudController::deleteEntity()). Counts the rows already in the trash too: they name it just as much, and are what the foreign key would trip on the day the serie is removed for good
    public function holdsContent(): bool
    {
        return !$this->books->isEmpty() || !$this->strips->isEmpty();
    }

    // What the site still shows of the serie, which is what setting it aside would leave pointing at a page answering 404 (see SerieCrudController::updateEntity). A book or a planche already hidden, or in the trash, is off the site too and holds nothing back - where holdsContent() above counts them all, the foreign key of the trash caring nothing for whether a row is shown
    public function holdsVisibleContent(): bool
    {
        foreach ($this->books as $book) {
            if (!$book->isHidden() && !$book->isDeleted()) {
                return true;
            }
        }

        foreach ($this->strips as $strip) {
            if (!$strip->isHidden() && !$strip->isDeleted()) {
                return true;
            }
        }

        return false;
    }

    // Also matches the legacy covers, stored with no kind before it was introduced
    public function getCovers(): Collection
    {
        return $this->medias->filter(fn (SerieMedia $m) => in_array($m->getKind(), ['cover', null], true));
    }

    public function addCover(SerieMedia $media): static
    {
        $media->setKind('cover');

        return $this->addMedia($media);
    }

    public function removeCover(SerieMedia $media): static
    {
        return $this->removeMedia($media);
    }

    public function getLogos(): Collection
    {
        return $this->medias->filter(fn (SerieMedia $m) => 'logo' === $m->getKind());
    }

    public function addLogo(SerieMedia $media): static
    {
        $media->setKind('logo');

        return $this->addMedia($media);
    }

    public function removeLogo(SerieMedia $media): static
    {
        return $this->removeMedia($media);
    }

    /**
     * The picture the page opens on, behind its title - the same "background" a book carries (see BookMediaKind), a serie being presented the same way.
     *
     * @return Collection<int, SerieMedia>
     */
    public function getBackgrounds(): Collection
    {
        return $this->medias->filter(fn (SerieMedia $m) => 'background' === $m->getKind());
    }

    public function addBackground(SerieMedia $media): static
    {
        $media->setKind('background');

        return $this->addMedia($media);
    }

    public function removeBackground(SerieMedia $media): static
    {
        return $this->removeMedia($media);
    }
}
