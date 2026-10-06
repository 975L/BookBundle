<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Entity;

use c975L\UiBundle\Contract\VichMediaNamableInterface;
use c975L\UiBundle\Contract\VichPrivateFileInterface;
use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

// A file an edition is sold as - its EPUB, its PDF, its printable booklet, its MP3 (see BookEditionFileKind, stored in Media::$kind) - kept out of public/ since it is what a buyer pays for. The catalog is its one home: the shop gets a copy of it, the stores fetching files read it here (see GooglePlayFeedController)
#[ORM\Entity]
#[Vich\Uploadable]
class BookEditionFile extends Media implements VichPrivateFileInterface, VichMediaNamableInterface
{
    #[ORM\ManyToOne(targetEntity: BookEdition::class, inversedBy: 'files')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?BookEdition $edition = null;

    // What the file sells for in the shop, tax included and in cents like ShopBundle's - a file of its own, the EPUB and the booklet of one edition rarely going for the same price. The ONIX announces the edition's price, not this one
    #[ORM\Column(nullable: true)]
    private ?int $price = null;

    public function getEdition(): ?BookEdition
    {
        return $this->edition;
    }

    public function setEdition(?BookEdition $edition): static
    {
        $this->edition = $edition;

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

    // The book the file belongs to, which the files' health check links the row back to (see BookFilesHealthCheckProvider)
    public function getBook(): ?Book
    {
        return $this->edition?->getBook();
    }

    public function getPrivateDirectory(): string
    {
        return 'private';
    }

    public function getVichMediaPath(): string
    {
        return self::MEDIA_DIRECTORY . '/editions/' . ($this->getBook()?->getSlug() ?? 'temp') . '-' . ($this->getKind() ?? 'file');
    }
}
