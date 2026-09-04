<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Entity;

use c975L\BookBundle\Contract\TrashableInterface;
use c975L\BookBundle\Entity\Trait\HideableTrait;
use c975L\BookBundle\Entity\Trait\TrashableTrait;
use c975L\BookBundle\Repository\BookCategoryRepository;
use c975L\ConfigBundle\Contract\UserInterface;
use c975L\UiBundle\Contract\HasBlocksInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Trait\HasBlocksTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

// What a book is about, where a Serie is what it belongs to: a book sits in one serie and carries as many categories as it deserves. Flat and many-to-many, which is what a "tag" is once it has a page of its own - a site naming them "Genres", "Thèmes" or "Rayons" translates the label rather than storing a second family (see the "label.category" keys of the book catalog). Optional from end to end: a site creating none shows none, and one leaving "book-route-categories" empty keeps the pages off the site altogether (see BookRoutePrefix)
#[ORM\Entity(repositoryClass: BookCategoryRepository::class)]
#[ORM\Table(name: 'book_category')]
#[UniqueEntity('slug')]
class BookCategory implements HasBlocksInterface, TrashableInterface, \Stringable
{
    use HasBlocksTrait;
    use HideableTrait;
    use TrashableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // The order the categories show in, which the editor lays by dragging them on their screen (see BookCategoryCrudController::reorder()): without it a list read in the order its rows were created
    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(length: 100)]
    private ?string $title = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $slug = null;

    // What the category page says of itself, and what its meta description is built from: a listing carrying nothing but a title is a page with no content of its own
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    // The code the same subject wears in the trade's own classifications - CLIL in France, Thema and BISAC abroad - which a publisher distributing through a wholesaler is asked for. Stored and never interpreted here: the bundle serves pages, the code travels with the category through the exports
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $creation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $modification = null;

    // What the category page says beyond the books it lists - composed in the back office with UiBundle's kinds, the same way a serie's page is
    #[ORM\ManyToMany(targetEntity: Block::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'book_category_block')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $blocks;

    #[ORM\ManyToMany(targetEntity: Book::class, mappedBy: 'categories')]
    private Collection $books;

    #[ORM\ManyToOne]
    private ?UserInterface $user = null;

    public function __construct()
    {
        $this->blocks = new ArrayCollection();
        $this->books = new ArrayCollection();
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

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

    // The owning side is the book, which is the one screen a category is picked on (see BookCrudController): both ends are kept in step here so a category loaded in memory lists what was just added to it
    public function addBook(Book $book): static
    {
        if (!$this->books->contains($book)) {
            $this->books->add($book);
            $book->addCategory($this);
        }

        return $this;
    }

    public function removeBook(Book $book): static
    {
        if ($this->books->removeElement($book)) {
            $book->removeCategory($this);
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
}
