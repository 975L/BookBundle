<?php

namespace c975L\BookBundle\Entity;

use c975L\BookBundle\Repository\BookRepository;
use c975L\ConfigBundle\Contract\UserInterface;
use c975L\UiBundle\Contract\HasBlocksInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Trait\HasBlocksTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: BookRepository::class)]
#[ORM\Table(name: 'book_book')]
#[UniqueEntity('slug')]
class Book implements HasBlocksInterface, \Stringable
{
    use HasBlocksTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $title = null;

    #[ORM\Column(length: 50)]
    private ?string $author = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $published = null;

    #[ORM\Column(length: 50)]
    private ?string $format = null;

    #[ORM\Column(length: 100)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $illustrator = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $creation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $modification = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $authorWebsite = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $illustratorWebsite = null;

    #[ORM\ManyToOne(targetEntity: Serie::class, inversedBy: 'books')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Serie $serie = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $age = null;

    // The volume number, which is the book's rank in its serie - named as Strip::$number is, the two being the same thing
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $number = null;

    #[ORM\ManyToMany(targetEntity: Block::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'book_book_block')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $blocks;

    #[ORM\OneToMany(targetEntity: BookMedia::class, mappedBy: 'book', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $medias;

    #[ORM\OneToMany(targetEntity: BookLink::class, mappedBy: 'book', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $links;

    #[ORM\OneToMany(targetEntity: BookEdition::class, mappedBy: 'book', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $editions;

    // The fields this site adds to a book and no other site has, held as one JSON payload rather than a column each - same reasoning as UiBundle's Block::$data: what a single catalog needs is then a form type it declares (see BookCustomizationProviderInterface::getDataFormType()), no schema migration for every app running this bundle. Anything the database itself has to filter, sort or join on stays a real column, an ISBN being a BookEdition row
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $data = null;

    #[ORM\OneToMany(targetEntity: BookVideo::class, mappedBy: 'book', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $videos;

    #[ORM\OneToMany(targetEntity: BookPresse::class, mappedBy: 'book', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $presses;

    #[ORM\OneToMany(targetEntity: BookMarketing::class, mappedBy: 'book', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $marketings;

    #[ORM\Column(nullable: true)]
    private ?int $pages = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $language = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'translation_id', referencedColumnName: 'id', nullable: true)]
    private ?self $translationBook = null;

    #[ORM\OneToMany(mappedBy: 'translationBook', targetEntity: self::class)]
    private Collection $translations;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $crowdfunding = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $crowdfundingEndDate = null;

    #[ORM\ManyToOne]
    private ?UserInterface $user = null;

    public function __construct()
    {
        $this->blocks = new ArrayCollection();
        $this->medias = new ArrayCollection();
        $this->links = new ArrayCollection();
        $this->editions = new ArrayCollection();
        $this->videos = new ArrayCollection();
        $this->presses = new ArrayCollection();
        $this->marketings = new ArrayCollection();
        $this->translations = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->title;
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

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(string $author): static
    {
        $this->author = $author;

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

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(string $format): static
    {
        $this->format = $format;

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

    public function setSummary(string $summary): static
    {
        $this->summary = $summary;

        return $this;
    }

    public function getIllustrator(): ?string
    {
        return $this->illustrator;
    }

    public function setIllustrator(?string $illustrator): static
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

    public function getAuthorWebsite(): ?string
    {
        return $this->authorWebsite;
    }

    public function setAuthorWebsite(?string $authorWebsite): static
    {
        $this->authorWebsite = $authorWebsite;

        return $this;
    }

    public function getIllustratorWebsite(): ?string
    {
        return $this->illustratorWebsite;
    }

    public function setIllustratorWebsite(?string $illustratorWebsite): static
    {
        $this->illustratorWebsite = $illustratorWebsite;

        return $this;
    }

    public function getSerie(): ?Serie
    {
        return $this->serie;
    }

    public function setSerie(?Serie $serie): static
    {
        $this->serie = $serie;

        return $this;
    }

    public function getAge(): ?string
    {
        return $this->age;
    }

    public function setAge(?string $age): static
    {
        $this->age = $age;

        return $this;
    }

    public function getNumber(): ?int
    {
        return $this->number;
    }

    public function setNumber(?int $number): static
    {
        $this->number = $number;

        return $this;
    }

    /** @return Collection<int, BookLink> */
    public function getLinks(): Collection
    {
        return $this->links;
    }

    // One platform's link, for a template asking for it by name rather than walking the collection. Which card a link prints in is not asked here: the group belongs to the vocabulary the site declares, which only a service reads (see BookCustomizationRegistry::getLinksOf())
    public function getLink(string $kind): ?BookLink
    {
        foreach ($this->links as $link) {
            if ($link->getKind() === $kind) {
                return $link;
            }
        }

        return null;
    }

    public function addLink(BookLink $link): static
    {
        if (!$this->links->contains($link)) {
            $this->links->add($link);
            $link->setBook($this);
        }

        return $this;
    }

    public function removeLink(BookLink $link): static
    {
        if ($this->links->removeElement($link) && $link->getBook() === $this) {
            $link->setBook(null);
        }

        return $this;
    }

    /** @return Collection<int, BookEdition> */
    public function getEditions(): Collection
    {
        return $this->editions;
    }

    // The editions a reader can actually get, an ISBN reserved ahead of its release not being one of them
    /** @return Collection<int, BookEdition> */
    public function getReleasedEditions(): Collection
    {
        return $this->editions->filter(static fn (BookEdition $edition) => $edition->isReleased());
    }

    // One edition by name, for a template asking for it rather than walking the collection
    public function getEdition(string $kind): ?BookEdition
    {
        foreach ($this->editions as $edition) {
            if ($edition->getKind() === $kind) {
                return $edition;
            }
        }

        return null;
    }

    // Out as far as a reader is concerned: at least one edition has appeared, whichever it is
    public function isReleased(): bool
    {
        return !$this->getReleasedEditions()->isEmpty();
    }

    public function addEdition(BookEdition $edition): static
    {
        if (!$this->editions->contains($edition)) {
            $this->editions->add($edition);
            $edition->setBook($this);
        }

        return $this;
    }

    public function removeEdition(BookEdition $edition): static
    {
        if ($this->editions->removeElement($edition) && $edition->getBook() === $this) {
            $edition->setBook(null);
        }

        return $this;
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        return $this->data ?? [];
    }

    /** @param array<string, mixed> $data */
    public function setData(?array $data): static
    {
        $this->data = $data;

        return $this;
    }

    // One field of what this site adds to a book, read by name so a template never spells out the payload's shape
    public function getDataValue(string $key, mixed $default = null): mixed
    {
        return $this->getData()[$key] ?? $default;
    }

    public function getMedias(): Collection
    {
        return $this->medias;
    }

    public function addMedia(BookMedia $media): static
    {
        if (!$this->medias->contains($media)) {
            $this->medias->add($media);
            $media->setBook($this);
        }

        return $this;
    }

    public function removeMedia(BookMedia $media): static
    {
        if ($this->medias->removeElement($media)) {
            // set the owning side to null (unless already changed)
            if ($media->getBook() === $this) {
                $media->setBook(null);
            }
        }

        return $this;
    }

    public function getVideos(): Collection
    {
        return $this->videos;
    }

    public function addVideo(BookVideo $video): static
    {
        if (!$this->videos->contains($video)) {
            $this->videos->add($video);
            $video->setBook($this);
        }

        return $this;
    }

    public function removeVideo(BookVideo $video): static
    {
        if ($this->videos->removeElement($video)) {
            // set the owning side to null (unless already changed)
            if ($video->getBook() === $this) {
                $video->setBook(null);
            }
        }

        return $this;
    }

    // The setter has to be kept, nothing works without it (04/07/2025)
    public function setPresses(Collection $presses): static
    {
        $this->presses = $presses;

        // Sets the owning side of the relation
        foreach ($presses as $presse) {
            $presse->setBook($this);
        }

        return $this;
    }

    public function getPresses(): Collection
    {
        return $this->presses;
    }

    public function addPresse(BookPresse $presse): static
    {
        if (!$this->presses->contains($presse)) {
            $this->presses->add($presse);
            $presse->setBook($this);
        }

        return $this;
    }

    public function removePresse(BookPresse $presse): static
    {
        if ($this->presses->removeElement($presse)) {
            // set the owning side to null (unless already changed)
            if ($presse->getBook() === $this) {
                $presse->setBook(null);
            }
        }

        return $this;
    }

    public function getMarketings(): Collection
    {
        return $this->marketings;
    }

    public function addMarketing(BookMarketing $marketing): static
    {
        if (!$this->marketings->contains($marketing)) {
            $this->marketings->add($marketing);
            $marketing->setBook($this);
        }

        return $this;
    }

    public function removeMarketing(BookMarketing $marketing): static
    {
        if ($this->marketings->removeElement($marketing)) {
            // set the owning side to null (unless already changed)
            if ($marketing->getBook() === $this) {
                $marketing->setBook(null);
            }
        }

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

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function getTranslationBook(): ?self
    {
        return $this->translationBook;
    }

    public function setTranslationBook(?self $translationBook): self
    {
        $this->translationBook = $translationBook;

        return $this;
    }

    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(self $book): self
    {
        if (!$this->translations->contains($book)) {
            $this->translations[] = $book;
            $book->setTranslationBook($this);
        }

        return $this;
    }

    public function getTranslation(string $language): ?self
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLanguage() === $language) {
                return $translation;
            }
        }

        return null;
    }

    public function getCrowdfunding(): ?string
    {
        return $this->crowdfunding;
    }

    public function setCrowdfunding(?string $crowdfunding): static
    {
        $this->crowdfunding = $crowdfunding;

        return $this;
    }

    public function getCrowdfundingEndDate(): ?\DateTime
    {
        return $this->crowdfundingEndDate;
    }

    public function setCrowdfundingEndDate(?\DateTime $crowdfundingEndDate): static
    {
        $this->crowdfundingEndDate = $crowdfundingEndDate;

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

    public function getEffectiveAuthor(): ?string
    {
        return $this->author ?? $this->serie?->getAuthor();
    }

    public function getEffectiveIllustrator(): ?string
    {
        return $this->illustrator ?? $this->serie?->getIllustrator();
    }

    public function getEffectiveAuthorWebsite(): ?string
    {
        return $this->authorWebsite ?? $this->serie?->getAuthorWebsite();
    }

    public function getEffectiveIllustratorWebsite(): ?string
    {
        return $this->illustratorWebsite ?? $this->serie?->getIllustratorWebsite();
    }
}
