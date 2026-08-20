<?php

namespace c975L\BookBundle\Entity;

use c975L\BookBundle\Enum\BookLinkKind;
use Doctrine\ORM\Mapping as ORM;

// Where a book is read, listened to or watched, one row per platform holding its address. Used to be four "epub_*" columns on the book itself, which is what made adding a store a migration on every site - the platform is now a value (see c975L\BookBundle\Enum\BookLinkKind) and no longer a column
#[ORM\Entity]
#[ORM\Table(name: 'book_link')]
class BookLink implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Book::class, inversedBy: 'links')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Book $book = null;

    #[ORM\Column(length: 30, enumType: BookLinkKind::class)]
    private ?BookLinkKind $kind = null;

    // The whole address, as SiteBundle's CollectionItem holds one: an affiliate identifier, a country, a format anchor are all part of what a platform hands over, and none of them is deducible from the book
    #[ORM\Column(length: 255)]
    private ?string $url = null;

    #[ORM\Column(nullable: true)]
    private ?int $position = null;

    // The version this platform sells or plays. A link is added from the version's own screen, so it always names one; the column stays nullable for the rows a site holds from before versions were edited apart
    #[ORM\ManyToOne(targetEntity: BookEdition::class, inversedBy: 'links')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?BookEdition $edition = null;

    public function getEdition(): ?BookEdition
    {
        return $this->edition;
    }

    public function setEdition(?BookEdition $edition): static
    {
        $this->edition = $edition;

        return $this;
    }

    public function __toString(): string
    {
        return $this->kind?->label() ?? '';
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

    public function getKind(): ?BookLinkKind
    {
        return $this->kind;
    }

    public function setKind(?BookLinkKind $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;

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

    public function getLabel(): ?string
    {
        return $this->kind?->label();
    }

    public function getIcon(): ?string
    {
        return $this->kind?->icon();
    }
}
