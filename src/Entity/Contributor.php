<?php

namespace c975L\BookBundle\Entity;

use c975L\BookBundle\Contract\TrashableInterface;
use c975L\BookBundle\Entity\Trait\HideableTrait;
use c975L\BookBundle\Entity\Trait\TrashableTrait;
use c975L\BookBundle\Repository\ContributorRepository;
use c975L\ConfigBundle\Contract\UserInterface;
use c975L\UiBundle\Contract\HasBlocksInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Trait\HasBlocksTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

// The person a book or a serie credits, who used to be a name retyped on each of them along with the address of their site - a spelling apart made a fourth author out of the same one, and the site had nowhere to say who they are
// One entity and not two: writing and illustrating are two roles, not two natures, and the same person often holds both. Which role is held is said by the relation - Book::$author and Book::$illustrator both point here - so a person exists once, with one page and one slug, however many hats they wear
#[ORM\Entity(repositoryClass: ContributorRepository::class)]
#[ORM\Table(name: 'book_contributor')]
#[UniqueEntity('slug')]
class Contributor implements HasBlocksInterface, TrashableInterface, \Stringable
{
    use HasBlocksTrait;
    use HideableTrait;
    use TrashableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // The order they show in, laid by dragging them on their screen (see ContributorCrudController::reorder()) - a publisher opens its list on the author it is known for, not on whoever was recorded first
    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    // The length the author column carried on Book and Serie, so no existing name is cut on the way in
    #[ORM\Column(length: 50)]
    private ?string $name = null;

    // Unique in database and not only through #[UniqueEntity]: the migration off the four string columns inserts in SQL, where no validator runs, and two names slugifying alike would both come in and make their page answer with an error
    #[ORM\Column(length: 100, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    // The address of their own site, held once by the person rather than once per book: the four author_website/illustrator_website columns said the same thing as many times as they were retyped
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $creation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $modification = null;

    #[ORM\ManyToMany(targetEntity: Block::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'book_contributor_block')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $blocks;

    #[ORM\OneToMany(targetEntity: Book::class, mappedBy: 'author')]
    #[ORM\OrderBy(['published' => 'DESC'])]
    private Collection $authoredBooks;

    #[ORM\OneToMany(targetEntity: Book::class, mappedBy: 'illustrator')]
    #[ORM\OrderBy(['published' => 'DESC'])]
    private Collection $illustratedBooks;

    #[ORM\OneToMany(targetEntity: Serie::class, mappedBy: 'author')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $authoredSeries;

    #[ORM\OneToMany(targetEntity: Serie::class, mappedBy: 'illustrator')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $illustratedSeries;

    #[Assert\Valid]
    #[ORM\OneToMany(targetEntity: ContributorMedia::class, mappedBy: 'contributor', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $medias;

    #[ORM\ManyToOne]
    private ?UserInterface $user = null;

    public function __construct()
    {
        $this->blocks = new ArrayCollection();
        $this->authoredBooks = new ArrayCollection();
        $this->illustratedBooks = new ArrayCollection();
        $this->authoredSeries = new ArrayCollection();
        $this->illustratedSeries = new ArrayCollection();
        $this->medias = new ArrayCollection();
    }

    // The name and nothing else: a template printing "{{ book.author }}" wrote the string this entity replaces, and goes on printing the same thing without being touched
    public function __toString(): string
    {
        return (string) $this->name;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;

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

    /** @return Collection<int, Book> */
    public function getAuthoredBooks(): Collection
    {
        return $this->authoredBooks;
    }

    /** @return Collection<int, Book> */
    public function getIllustratedBooks(): Collection
    {
        return $this->illustratedBooks;
    }

    /** @return Collection<int, Serie> */
    public function getAuthoredSeries(): Collection
    {
        return $this->authoredSeries;
    }

    /** @return Collection<int, Serie> */
    public function getIllustratedSeries(): Collection
    {
        return $this->illustratedSeries;
    }

    // Every book they had a hand in, whichever hat they wore - what their page lists, an illustrated album belonging on it as much as a text. Written once here rather than merged again in each template, and deduplicated: the two collections overlap on a book they both wrote and drew
    /** @return list<Book> */
    public function getBooks(): array
    {
        $books = [];
        foreach ([...$this->authoredBooks, ...$this->illustratedBooks] as $book) {
            $books[spl_object_id($book)] = $book;
        }

        return array_values($books);
    }

    // The same for the series, read by their page under the books
    /** @return list<Serie> */
    public function getSeries(): array
    {
        $series = [];
        foreach ([...$this->authoredSeries, ...$this->illustratedSeries] as $serie) {
            $series[spl_object_id($serie)] = $serie;
        }

        return array_values($series);
    }

    /** @return Collection<int, ContributorMedia> */
    public function getMedias(): Collection
    {
        return $this->medias;
    }

    public function addMedia(ContributorMedia $media): static
    {
        if (!$this->medias->contains($media)) {
            $this->medias->add($media);
            $media->setContributor($this);
        }

        return $this;
    }

    public function removeMedia(ContributorMedia $media): static
    {
        if ($this->medias->removeElement($media) && $media->getContributor() === $this) {
            $media->setContributor(null);
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

    // Their photograph, the one their card and the top of their page are drawn with
    public function getPortraits(): Collection
    {
        return $this->medias->filter(fn (ContributorMedia $m) => 'portrait' === $m->getKind());
    }

    public function addPortrait(ContributorMedia $media): static
    {
        $media->setKind('portrait');

        return $this->addMedia($media);
    }

    public function removePortrait(ContributorMedia $media): static
    {
        return $this->removeMedia($media);
    }

    // The picture their page opens on, behind their name - the same "background" a book and a serie carry
    public function getBackgrounds(): Collection
    {
        return $this->medias->filter(fn (ContributorMedia $m) => 'background' === $m->getKind());
    }

    public function addBackground(ContributorMedia $media): static
    {
        $media->setKind('background');

        return $this->addMedia($media);
    }

    public function removeBackground(ContributorMedia $media): static
    {
        return $this->removeMedia($media);
    }

    // Whether a book or a serie still credits them - what refuses them the trash, the same rule a serie holding a book answers to (see ContributorCrudController::deleteEntity()). Counts the rows already in the trash too: they name them just as much, and are what the foreign key would trip on the day the person is removed for good
    public function holdsContent(): bool
    {
        return !$this->authoredBooks->isEmpty()
            || !$this->illustratedBooks->isEmpty()
            || !$this->authoredSeries->isEmpty()
            || !$this->illustratedSeries->isEmpty();
    }

    // What the site still credits them on, which is what setting them aside would leave pointing at a page answering 404 (see ContributorCrudController::updateEntity). A book or a serie already hidden, or in the trash, is off the site too and holds nothing back
    public function holdsVisibleContent(): bool
    {
        return array_any([...$this->getBooks(), ...$this->getSeries()], fn ($entity) => !$entity->isHidden() && !$entity->isDeleted());
    }
}
