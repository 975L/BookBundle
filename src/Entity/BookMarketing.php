<?php

namespace c975L\BookBundle\Entity;

use c975L\UiBundle\Contract\VichMediaNamableInterface;
use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
class BookMarketing extends Media implements VichMediaNamableInterface
{
    #[ORM\ManyToOne(targetEntity: Book::class, inversedBy: 'marketings')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Book $book = null;

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
        return self::MEDIA_DIRECTORY . '/marketing/' . ($this->book?->getSlug() ?? 'temp');
    }
}
