<?php

namespace c975L\BookBundle\Entity;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\Media;
use c975L\UiBundle\Contract\VichMediaNamableInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
class BookMedia extends Media implements VichMediaNamableInterface
{
    #[Vich\UploadableField(mapping: 'books', fileNameProperty: 'name', size: 'size')]
    protected ?File $file = null;

    #[ORM\ManyToOne(targetEntity: Book::class, inversedBy: 'medias')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Book $book = null;

    public function getMappingName(): string
    {
        return 'books';
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
        return 'medias/books/' . ($this->book?->getSlug() ?? 'temp');
    }
}
