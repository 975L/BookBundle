<?php

namespace c975L\BookBundle\Entity;

use App\Entity\User;
use c975L\BookBundle\Repository\SerieRepository;
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
class Serie implements HasBlocksInterface
{
    use HasBlocksTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $title = null;

    #[ORM\Column(length: 50)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $language = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $creation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $modification = null;

    #[ORM\ManyToMany(targetEntity: Block::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'book_serie_block')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $blocks;

    #[ORM\OneToMany(targetEntity: Book::class, mappedBy: 'serie')]
    private Collection $books;

    #[ORM\OneToMany(targetEntity: SerieMedia::class, mappedBy: 'serie', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(["position" => "ASC"])]
    private Collection $medias;

    #[ORM\ManyToOne]
    private ?User $user = null;

    public function __construct()
    {
        $this->blocks = new ArrayCollection();
        $this->books = new ArrayCollection();
        $this->medias = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->title;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): static
    {
        $this->language = $language;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCovers(): Collection
    {
        return $this->medias->filter(fn(SerieMedia $m) => $m->getKind() !== 'logo');
    }

    public function addCover(SerieMedia $media): static
    {
        $media->setKind(null);
        return $this->addMedia($media);
    }

    public function removeCover(SerieMedia $media): static
    {
        return $this->removeMedia($media);
    }

    public function getLogos(): Collection
    {
        return $this->medias->filter(fn(SerieMedia $m) => $m->getKind() === 'logo');
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
}
