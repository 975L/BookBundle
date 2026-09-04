<?php

namespace c975L\BookBundle\Entity;

use c975L\BookBundle\Contract\PlatformLinkInterface;
use Doctrine\ORM\Mapping as ORM;

// Where the books someone signed are bought, one row per platform holding its address - their own page at a store, not one book's (see BookLink, which the page of a book reads). The two are kept apart as the medias are (see ContributorMedia): a person's row belongs to no book
#[ORM\Entity]
#[ORM\Table(name: 'book_contributor_link')]
class ContributorLink implements PlatformLinkInterface, \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Contributor::class, inversedBy: 'links')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Contributor $contributor = null;

    // The platform, stored as the site's own word and read from the very vocabulary a book's links are read from (see BookCustomizationRegistry): a store selling both is declared once
    #[ORM\Column(length: 30)]
    private ?string $kind = null;

    // The whole address, as the platform hands it over: an affiliate identifier, a country, an author identifier are all part of it, and none of them is deducible from the person
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

    public function getContributor(): ?Contributor
    {
        return $this->contributor;
    }

    public function setContributor(?Contributor $contributor): static
    {
        $this->contributor = $contributor;

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
