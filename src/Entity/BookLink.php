<?php

namespace c975L\BookBundle\Entity;

use c975L\BookBundle\Contract\PlatformLinkInterface;
use Doctrine\ORM\Mapping as ORM;

// Where a book is read, listened to or watched, one row per platform holding its address. Used to be four "epub_*" columns on the book itself, which is what made adding a store a migration on every site - the platform is now a value (see c975L\BookBundle\Enum\BookLinkKind) and no longer a column
#[ORM\Entity]
#[ORM\Table(name: 'book_link')]
class BookLink implements PlatformLinkInterface, \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Book::class, inversedBy: 'links')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Book $book = null;

    // The platform, stored as the site's own word rather than as a case of an enum the bundle would have to know (see c975L\BookBundle\Contract\BookCustomizationProviderInterface). What it is called, the card it prints in and its icon are read from that vocabulary (see BookCustomizationRegistry)
    #[ORM\Column(length: 30)]
    private ?string $kind = null;

    // The whole address, as SiteBundle's CollectionItem holds one: an affiliate identifier, a country, a format anchor are all part of what a platform hands over, and none of them is deducible from the book
    #[ORM\Column(length: 255)]
    private ?string $url = null;

    #[ORM\Column(nullable: true)]
    private ?int $position = null;

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
}
