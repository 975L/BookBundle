<?php

namespace c975L\BookBundle\Entity;

use DateTimeInterface;
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Entity\BookVideo;
use c975L\BookBundle\Entity\BookPresse;
use c975L\BookBundle\Entity\BookMarketing;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use c975L\BookBundle\Repository\BookRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity(repositoryClass: BookRepository::class)]
class Book
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $title = null;

    #[ORM\Column(length: 50)]
    private ?string $author = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?DateTimeInterface $published = null;

    #[ORM\Column(length: 50)]
    private ?string $format = null;

    #[ORM\Column(length: 100)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(length: 13, nullable: true)]
    private ?string $isbn = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $illustrator = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $creation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $modification = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $authorWebsite = null;

    #[ORM\Column(length: 20, nullable: true)]
    private $epubGplay;

    #[ORM\Column(length: 100, nullable: true)]
    private $epubFnac;

    #[ORM\Column(length: 100, nullable: true)]
    private $epubKobo;

    #[ORM\ManyToOne(targetEntity: Serie::class, inversedBy: 'books')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Serie $serie = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $age = null;

    #[ORM\Column(length: 13, nullable: true)]
    private ?string $isbnDigital = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $epubApple = null;

    #[ORM\OneToMany(targetEntity: BookMedia::class, mappedBy: 'book', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(["position" => "ASC"])]
    private Collection $medias;

    #[ORM\OneToMany(targetEntity: BookVideo::class, mappedBy: 'book', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(["position" => "ASC"])]
    private Collection $videos;

    #[ORM\OneToMany(targetEntity: BookPresse::class, mappedBy: 'book', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(["position" => "ASC"])]
    private Collection $presses;

    #[ORM\OneToMany(targetEntity: BookMarketing::class, mappedBy: 'book', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(["position" => "ASC"])]
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

    public function __construct()
    {
        $this->medias = new ArrayCollection();
        $this->videos = new ArrayCollection();
        $this->presses = new ArrayCollection();
        $this->marketings = new ArrayCollection();
        $this->translations = new ArrayCollection();
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

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(string $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getPublished(): ?DateTimeInterface
    {
        return $this->published;
    }

    public function setPublished(?DateTimeInterface $published): static
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

    public function getIsbn(): ?string
    {
        return $this->isbn;
    }

    public function setIsbn(?string $isbn): static
    {
        $this->isbn = $isbn;

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

    public function getCreation(): ?DateTimeInterface
    {
        return $this->creation;
    }

    public function setCreation(DateTimeInterface $creation): static
    {
        $this->creation = $creation;

        return $this;
    }

    public function getModification(): ?DateTimeInterface
    {
        return $this->modification;
    }

    public function setModification(DateTimeInterface $modification): static
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

    public function setEpubGplay(?string $epubGplay): self
    {
        $this->epubGplay = $epubGplay;

        return $this;
    }

    public function getEpubGplay(): ?string
    {
        return $this->epubGplay;
    }

    public function setEpubKobo(?string $epubKobo): self
    {
        $this->epubKobo = $epubKobo;

        return $this;
    }

    public function getEpubKobo(): ?string
    {
        return $this->epubKobo;
    }

    public function setEpubFnac(?string $epubFnac): self
    {
        $this->epubFnac = $epubFnac;

        return $this;
    }

    public function getEpubFnac(): ?string
    {
        return $this->epubFnac;
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

    public function getIsbnDigital(): ?string
    {
        return $this->isbnDigital;
    }

    public function setIsbnDigital(?string $isbnDigital): static
    {
        $this->isbnDigital = $isbnDigital;

        return $this;
    }

    public function getEpubApple(): ?string
    {
        return $this->epubApple;
    }

    public function setEpubApple(?string $epubApple): static
    {
        $this->epubApple = $epubApple;

        return $this;
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

    // CONSERVER le setter sinon cela ne fonctionne pas... (04/07/2025)
    public function setPresses(Collection $presses): static
    {
        $this->presses = $presses;

        // Important : établir la relation bidirectionnelle
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
}
