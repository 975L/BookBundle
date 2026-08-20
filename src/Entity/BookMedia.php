<?php

namespace c975L\BookBundle\Entity;

use c975L\UiBundle\Contract\VichMediaNamableInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
class BookMedia extends Media implements VichMediaNamableInterface
{
    #[Vich\UploadableField(mapping: 'block_media', fileNameProperty: 'name', size: 'size')]
    protected ?File $file = null;

    #[ORM\ManyToOne(targetEntity: Book::class, inversedBy: 'medias')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Book $book = null;

    // The version this file belongs to - the pages of the illustrated one are not those of the original. A file is added from the version's own screen, so it always names one; the column stays nullable for the rows a site holds from before versions were edited apart
    #[ORM\ManyToOne(targetEntity: BookEdition::class, inversedBy: 'medias')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?BookEdition $edition = null;

    public function getEdition(): ?BookEdition
    {
        return $this->edition;
    }

    public function setEdition(?BookEdition $edition): static
    {
        $this->edition = $edition;

        return $this;
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

    public function getVichMediaPath(): string
    {
        return self::MEDIA_DIRECTORY . '/books/' . $this->getKind() . '-' . ($this->book?->getSlug() ?? 'temp');
    }
}
