<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Entity\Trait;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

// The day a book's file shows, shared by the rows coming out one at a time - a document to print, a page to colour on screen. One column of book_media, single-table inheritance letting both subclasses map it
trait PublishedOnTrait
{
    // None: it shows as soon as it is saved
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedOn = null;

    public function getPublishedOn(): ?\DateTimeImmutable
    {
        return $this->publishedOn;
    }

    public function setPublishedOn(?\DateTimeImmutable $publishedOn): static
    {
        $this->publishedOn = $publishedOn;

        return $this;
    }

    // Compared day by day like Book::isToBePublished(): a row dated today is out, whatever the hour
    public function isPublished(): bool
    {
        return null === $this->publishedOn || $this->publishedOn->format('Ymd') <= new \DateTime()->format('Ymd');
    }
}
