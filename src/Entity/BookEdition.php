<?php

namespace c975L\BookBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

// One published form of a book - the paperback, the ebook, the audiobook, the illustrated one - each with its own ISBN and its own release date. Used to be three "isbn_*" columns on the book itself, which said nothing of when each came out and could not hold a fourth: an edition is a row now, its kind a value the site names (see c975L\BookBundle\Contract\BookCustomizationProviderInterface), the same move BookLink made for the stores
#[ORM\Entity]
#[ORM\Table(name: 'book_edition')]
class BookEdition implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Book::class, inversedBy: 'editions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Book $book = null;

    #[ORM\Column(length: 30)]
    private ?string $kind = null;

    #[ORM\Column(length: 13, nullable: true)]
    private ?string $isbn = null;

    // Empty for as long as the edition is not out, which is what a page says in its place ("à paraître") - an ISBN is reserved long before the book it names
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $published = null;

    // Only when it differs from the book's own count, an illustrated edition rarely holding as many pages as the text it comes from
    #[ORM\Column(nullable: true)]
    private ?int $pages = null;

    #[ORM\Column(nullable: true)]
    private ?int $position = null;

    // The files of this edition alone - its pages, its recording, its cover. Held here rather than found by walking the book's own medias: a version is edited on its own screen (see c975L\BookBundle\Controller\Management\BookEditionCrudController), where the files it holds are the ones in front of the eye
    /** @var Collection<int, BookMedia> */
    #[ORM\OneToMany(targetEntity: BookMedia::class, mappedBy: 'edition', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $medias;

    // Where this edition is sold, read or listened to - one row per platform, belonging to the version the platform actually carries
    /** @var Collection<int, BookLink> */
    #[ORM\OneToMany(targetEntity: BookLink::class, mappedBy: 'edition', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $links;

    public function __construct()
    {
        $this->medias = new ArrayCollection();
        $this->links = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->kind;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBook(): ?Book
    {
        return $this->book;
    }

    public function setBook(?Book $book): static
    {
        $this->book = $book;

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

    public function getIsbn(): ?string
    {
        return $this->isbn;
    }

    public function setIsbn(?string $isbn): static
    {
        $this->isbn = $isbn;

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

    // Out as far as a reader is concerned: a date in the future is an announcement, not a book to open
    public function isReleased(): bool
    {
        return null !== $this->published && $this->published <= new \DateTime();
    }

    public function getPages(): ?int
    {
        return $this->pages;
    }

    // The count to show when this edition has none of its own - the book's, as a fallback never written back to the column
    public function getDisplayedPages(): ?int
    {
        return $this->pages ?? $this->book?->getPages();
    }

    public function setPages(?int $pages): static
    {
        $this->pages = $pages;

        return $this;
    }

    // The files this edition holds, narrowed to one kind for a template asking for its pages and not its recording
    /** @return list<BookMedia> */
    public function getMedias(?string $kind = null): array
    {
        $medias = $this->medias->toArray();

        if (null !== $kind) {
            $medias = array_filter($medias, static fn (BookMedia $media) => $media->getKind() === $kind);
        }

        return array_values($medias);
    }

    // The book is set along with the edition: the path a file is stored under is built on the book's slug (see BookMedia::getVichMediaPath())
    public function addMedia(BookMedia $media): static
    {
        if (!$this->medias->contains($media)) {
            $this->medias->add($media);
            $media->setEdition($this);
            $this->book?->addMedia($media);
        }

        return $this;
    }

    // Removing a file from its version deletes it, the row as much as the uploaded file - a file belongs to one version and to no other
    public function removeMedia(BookMedia $media): static
    {
        if ($this->medias->removeElement($media) && $media->getEdition() === $this) {
            $media->setEdition(null);
        }

        return $this;
    }

    /** @return list<BookLink> */
    public function getLinks(): array
    {
        return $this->links->toArray();
    }

    public function addLink(BookLink $link): static
    {
        if (!$this->links->contains($link)) {
            $this->links->add($link);
            $link->setEdition($this);
            $this->book?->addLink($link);
        }

        return $this;
    }

    public function removeLink(BookLink $link): static
    {
        if ($this->links->removeElement($link) && $link->getEdition() === $this) {
            $link->setEdition(null);
        }

        return $this;
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
}
