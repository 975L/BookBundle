<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Entity;

use c975L\BookBundle\Enum\BookChannel;
use c975L\BookBundle\Enum\BookEditionFileKind;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

// An edition a book comes out under - paper, digital, audio - each with its ISBN and its release date. These were three "isbn_*" columns on the book itself, which said nothing of when each came out and could hold no fourth: an edition is a row now, its name a value the site can redeclare (see c975L\BookBundle\Contract\BookCustomizationProviderInterface), the same gesture as BookLink for the shops. An edition is not a version of the text: a rewritten, revised or newly illustrated book comes out in all those editions too, and is therefore a book apart (see Book::$newerVersion)
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

    // The public price of this edition, tax included and in cents like ShopBundle's - what the ONIX feed announces to the stores. Empty, the edition is announced without a price
    #[ORM\Column(nullable: true)]
    private ?int $price = null;

    // ISO 4217, upper case as ONIX writes it
    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    private string $currency = 'EUR';

    // The files this edition is sold as, private, one per kind at most (see BookEditionFile and BookEditionFileKind)
    /** @var Collection<int, BookEditionFile> */
    #[ORM\OneToMany(targetEntity: BookEditionFile::class, mappedBy: 'edition', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $files;

    // Where the edition is handed out (see BookChannel) - none ticked, it is shown on the site and sent nowhere
    /** @var list<string> */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $channels = [];

    public function __construct()
    {
        $this->files = new ArrayCollection();
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

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(?int $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): static
    {
        $this->currency = strtoupper(trim((string) $currency)) ?: 'EUR';

        return $this;
    }

    /** @return Collection<int, BookEditionFile> */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    // The file of one kind, null when the edition is not sold as it
    public function getFileOf(BookEditionFileKind $kind): ?BookEditionFile
    {
        foreach ($this->files as $file) {
            if ($kind->value === $file->getKind()) {
                return $file;
            }
        }

        return null;
    }

    // Puts a file in the place of its kind - null, or a file whose upload was deleted, taking the kind's file away (orphanRemoval drops its row)
    public function setFileOf(BookEditionFileKind $kind, ?BookEditionFile $file): static
    {
        $current = $this->getFileOf($kind);
        if (null !== $current && $current !== $file) {
            $this->files->removeElement($current);
        }

        if (null === $file || (null === $file->getName() && null === $file->getFile())) {
            if (null !== $file) {
                $this->files->removeElement($file);
            }

            return $this;
        }

        $file->setKind($kind->value)->setEdition($this);
        if (!$this->files->contains($file)) {
            $this->files->add($file);
        }

        return $this;
    }

    /** @return list<string> */
    public function getChannels(): array
    {
        return $this->channels;
    }

    /** @param list<string> $channels */
    public function setChannels(array $channels): static
    {
        $this->channels = array_values(array_unique($channels));

        return $this;
    }

    public function hasChannel(BookChannel $channel): bool
    {
        return \in_array($channel->value, $this->channels, true);
    }
}
