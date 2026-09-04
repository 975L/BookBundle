<?php

namespace c975L\BookBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

// The part someone takes in a book - narrating it, translating it - held by the row that joins the two rather than by a column of its own: a person is a narrator on one title and a translator on the next, and a column per role would have to be added to every catalog the day one credits a colourist. Author and illustrator are not written here: they are the book's own columns, the only two a book inherits from its serie (see Book::getEffectiveAuthor())
#[ORM\Entity]
#[ORM\Table(name: 'book_book_contributor')]
#[ORM\UniqueConstraint(name: 'book_contributor_role', columns: ['book_id', 'contributor_id', 'role'])]
class BookContributor implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Book::class, inversedBy: 'contributors')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Book $book = null;

    // Not deleted along with the book's row: someone credited elsewhere keeps their page, and the trash asks them first (see Contributor::holdsContent())
    #[ORM\ManyToOne(targetEntity: Contributor::class, inversedBy: 'credits')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Contributor $contributor = null;

    #[ORM\Column(length: 30)]
    private ?string $role = null;

    // The order the credits are laid in the back office, which is the order the book's page prints them
    #[ORM\Column(nullable: true)]
    private ?int $position = null;

    // The name of the person, the same as a contributor prints: what the back office writes on the collapsed row
    public function __toString(): string
    {
        return (string) $this->contributor;
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

    public function getContributor(): ?Contributor
    {
        return $this->contributor;
    }

    public function setContributor(?Contributor $contributor): static
    {
        $this->contributor = $contributor;

        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $role): static
    {
        $this->role = $role;

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
