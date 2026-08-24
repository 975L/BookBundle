<?php

namespace c975L\BookBundle\Entity;

use c975L\BookBundle\Contract\TrashableInterface;
use c975L\BookBundle\Entity\Trait\TrashableTrait;
use c975L\BookBundle\Enum\BookLinkGroup;
use c975L\BookBundle\Enum\BookLinkKind;
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
class Book implements HasBlocksInterface, TrashableInterface, \Stringable
{
    use HasBlocksTrait;
    use TrashableTrait;

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

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $language = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'translation_id', referencedColumnName: 'id', nullable: true)]
    private ?self $translationBook = null;

    #[ORM\OneToMany(mappedBy: 'translationBook', targetEntity: self::class)]
    private Collection $translations;

    // The book that replaces this one - a text newly illustrated, revised or reset comes out as a book of its own, with its own ISBN, its own release date and its own page, rather than as one more edition: an edition is a format (paper, digital, audio), and every format exists for both versions. A book pointing at a newer one is still sold and still read, it only stops being what the catalog lists (see BookRepository::publishedQueryBuilder())
    // A one-to-one link and not a collection: a version replaces one only and is replaced by one only, and both ends read - the old one's page names the one replacing it as much as the reverse (see BookVersionExtension). A third version chains onto the second, it is not added beside it
    #[ORM\OneToOne(targetEntity: self::class, inversedBy: 'previousVersion')]
    #[ORM\JoinColumn(name: 'version_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?self $newerVersion = null;

    // The book this one replaces, read from the row that names it - the inverse side carries no column of its own
    #[ORM\OneToOne(mappedBy: 'newerVersion', targetEntity: self::class)]
    private ?self $previousVersion = null;

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

    // The book's recordings, whatever edition they come out under: it is a file of the book, and it is under "Listen" that it is dropped as it is listened to (see BookSectionsExtension::audioMedias(), which reads them the same way for the page)
    // Recognized by their kind as much as by their extension: a row just added has no file yet, and the kind is what the editor picks first (see BookMediaType)
    /** @return Collection<int, BookMedia> */
    public function getAudios(): Collection
    {
        return $this->medias->filter(static fn (BookMedia $media): bool => str_starts_with((string) $media->getKind(), 'audio') || str_starts_with($media->getMimeType(), 'audio/'));
    }

    public function addAudio(BookMedia $media): static
    {
        return $this->addMedia($media);
    }

    public function removeAudio(BookMedia $media): static
    {
        return $this->removeMedia($media);
    }

    // The platforms sorted by the gesture they serve, as the page sorts them: the bookshops under "Buy", the podcast apps under "Listen", the channels under "Watch" (see BookSectionsExtension::book())
    // Three views of one collection, and not three relations: a link belongs to the book only, and its kind is what says which gesture it reads under. Additions and removals therefore go through the collection itself, which stays the only one carrying the rows
    /** @return Collection<int, BookLink> */
    public function getBuyLinks(): Collection
    {
        return $this->linksOfGroups(BookLinkGroup::Epub);
    }

    public function addBuyLink(BookLink $link): static
    {
        return $this->addLink($link);
    }

    public function removeBuyLink(BookLink $link): static
    {
        return $this->removeLink($link);
    }

    /** @return Collection<int, BookLink> */
    public function getListenLinks(): Collection
    {
        return $this->linksOfGroups(BookLinkGroup::Audio, BookLinkGroup::Podcast);
    }

    public function addListenLink(BookLink $link): static
    {
        return $this->addLink($link);
    }

    public function removeListenLink(BookLink $link): static
    {
        return $this->removeLink($link);
    }

    /** @return Collection<int, BookLink> */
    public function getWatchLinks(): Collection
    {
        return $this->linksOfGroups(BookLinkGroup::Video);
    }

    public function addWatchLink(BookLink $link): static
    {
        return $this->addLink($link);
    }

    public function removeWatchLink(BookLink $link): static
    {
        return $this->removeLink($link);
    }

    // What none of the three claims: a kind a site declared itself and this bundle knows nothing about (see BookCustomizationProviderInterface::getLinkKinds()). Without this view such a row would be editable nowhere - the screen only opens it when there is one
    /** @return Collection<int, BookLink> */
    public function getOtherLinks(): Collection
    {
        return $this->links->filter(static fn (BookLink $link): bool => !BookLinkKind::tryFrom((string) $link->getKind()) instanceof BookLinkKind);
    }

    public function addOtherLink(BookLink $link): static
    {
        return $this->addLink($link);
    }

    public function removeOtherLink(BookLink $link): static
    {
        return $this->removeLink($link);
    }

    /** @return Collection<int, BookLink> */
    private function linksOfGroups(BookLinkGroup ...$groups): Collection
    {
        return $this->links->filter(
            static fn (BookLink $link): bool => \in_array(BookLinkKind::tryFrom((string) $link->getKind())?->group(), $groups, true)
        );
    }

    /** @return Collection<int, BookEdition> */
    public function getEditions(): Collection
    {
        return $this->editions;
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

    /** @return Collection<int, BookMedia> */
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

    // The three images a book carries as a whole rather than as one of its versions: its first cover, its fourth, and the backdrop its page opens on. The cover is also what a shared link is drawn with - a book has one image to introduce itself, not two. Each is uploaded on a field of its own (see BookCrudController), which is what sets the kind - a site never has to name any of them in its own vocabulary (see BookCustomizationProviderInterface::getMediaKinds()).
    // The kind, set by the field each is uploaded on, is what tells them from the pages, the recordings and the flipbooks the book also carries
    public function getCovers(): Collection
    {
        return $this->mediasOfKind('cover');
    }

    public function addCover(BookMedia $media): static
    {
        $media->setKind('cover');

        return $this->addMedia($media);
    }

    public function removeCover(BookMedia $media): static
    {
        return $this->removeMedia($media);
    }

    public function getBackCovers(): Collection
    {
        return $this->mediasOfKind('cover_back');
    }

    public function addBackCover(BookMedia $media): static
    {
        $media->setKind('cover_back');

        return $this->addMedia($media);
    }

    public function removeBackCover(BookMedia $media): static
    {
        return $this->removeMedia($media);
    }

    // The flipbook: the video turning the pages of the book, one for the whole book
    public function getFlipbooks(): Collection
    {
        return $this->mediasOfKind('flipbook');
    }

    public function addFlipbook(BookMedia $media): static
    {
        $media->setKind('flipbook');

        return $this->addMedia($media);
    }

    public function removeFlipbook(BookMedia $media): static
    {
        return $this->removeMedia($media);
    }

    // The pages a reader leafs through before buying: they belong to the book and not to one of its editions, unlike an album's pages, which are those of the edition they came out in
    public function getExtracts(): Collection
    {
        return $this->mediasOfKind('extract');
    }

    public function addExtract(BookMedia $media): static
    {
        $media->setKind('extract');

        return $this->addMedia($media);
    }

    public function removeExtract(BookMedia $media): static
    {
        return $this->removeMedia($media);
    }

    public function getBackgrounds(): Collection
    {
        return $this->mediasOfKind('background');
    }

    public function addBackground(BookMedia $media): static
    {
        $media->setKind('background');

        return $this->addMedia($media);
    }

    public function removeBackground(BookMedia $media): static
    {
        return $this->removeMedia($media);
    }

    // The book's own files of one kind, in the order they were sorted in
    public function mediasOfKind(string $kind): Collection
    {
        return $this->medias->filter(static fn (BookMedia $media): bool => $kind === $media->getKind());
    }

    /** @return Collection<int, BookVideo> */
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

    /** @return Collection<int, BookPresse> */
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

    /** @return Collection<int, BookMarketing> */
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

    public function getNewerVersion(): ?self
    {
        return $this->newerVersion;
    }

    // Both ends are laid together: the link reads from either side, and leaving the reverse empty until the next load would deprive the old version's page of the button leading to the new one
    public function setNewerVersion(?self $newerVersion): self
    {
        if ($newerVersion === $this->newerVersion) {
            return $this;
        }

        // The book replaced until now is no longer replaced by this one
        if ($this->newerVersion instanceof self) {
            $this->newerVersion->previousVersion = null;
        }

        $this->newerVersion = $newerVersion;

        if ($newerVersion instanceof self) {
            // A book replaces one version only: the one it replaced before is freed
            if ($newerVersion->previousVersion instanceof self) {
                $newerVersion->previousVersion->newerVersion = null;
            }

            $newerVersion->previousVersion = $this;
        }

        return $this;
    }

    public function getPreviousVersion(): ?self
    {
        return $this->previousVersion;
    }

    public function setPreviousVersion(?self $previousVersion): self
    {
        if ($previousVersion === $this->previousVersion) {
            return $this;
        }

        if ($this->previousVersion instanceof self) {
            $this->previousVersion->newerVersion = null;
        }

        $this->previousVersion = $previousVersion;

        if ($previousVersion instanceof self) {
            if ($previousVersion->newerVersion instanceof self) {
                $previousVersion->newerVersion->previousVersion = null;
            }

            $previousVersion->newerVersion = $this;
        }

        return $this;
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
