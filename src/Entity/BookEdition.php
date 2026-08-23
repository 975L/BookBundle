<?php

namespace c975L\BookBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

// An edition a book comes out under - paper, digital, audio - each with its ISBN and its release date. These were three "isbn_*" columns on the book itself, which said nothing of when each came out and could hold no fourth: an edition is a row now, its name a value the site can redeclare (see c975L\BookBundle\Contract\BookCustomizationProviderInterface), the same gesture as BookLink for the shops
// An edition is not a version of the text: a rewritten, revised or newly illustrated book comes out in all those editions too, and is therefore a book apart (see Book::$newerVersion)
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

    #[ORM\Column(nullable: true)]
    private ?int $pages = null;

    // What this version is physically - "15 x 21 cm", "PDF", "MP3" - held here rather than on the book: a paperback and an ebook of the same story are never the same object
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $format = null;

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

    public function getIsbn(): ?string
    {
        return $this->isbn;
    }

    public function setIsbn(?string $isbn): static
    {
        $this->isbn = $isbn;

        return $this;
    }

    public function getPages(): ?int
    {
        return $this->pages;
    }

    public function setPages(?int $pages): static
    {
        $this->pages = $pages;

        return $this;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(?string $format): static
    {
        $this->format = $format;

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
