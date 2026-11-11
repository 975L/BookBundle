<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Entity;

use c975L\BookBundle\Entity\Trait\PublishedOnTrait;
use c975L\UiBundle\Contract\VichMediaNamableInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

// A document a reader prints from a book's page - a colouring page, an activity sheet, a pattern. Always a PDF, whose first page UiBundle's VichPdfThumbnailListener turns into the thumbnail the page shows, and never resized: it is printed at the size it was drawn. Its kind, a word of the site's, is the level or the family it is grouped under (see BookCustomizationProviderInterface::getPrintableKinds())
#[ORM\Entity]
#[Vich\Uploadable]
class BookPrintable extends Media implements VichMediaNamableInterface
{
    use PublishedOnTrait;

    #[ORM\ManyToOne(targetEntity: Book::class, inversedBy: 'printables')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Book $book = null;

    // Never stored: the words the site names its documents by, set right before an upload (see BookPrintableNamer)
    private string $namePrefix = '';

    // Checked on the row rather than on the form's field, shared by every collection holding anything: an image here would have no document to print and no thumbnail to show
    #[Assert\Callback]
    public function validatePdf(ExecutionContextInterface $context): void
    {
        if (null === $this->file || 'pdf' === $this->file->guessExtension()) {
            return;
        }

        $context->buildViolation('label.printable_not_pdf')
            ->atPath('file')
            ->addViolation();
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

    public function getNamePrefix(): string
    {
        return $this->namePrefix;
    }

    public function setNamePrefix(string $namePrefix): static
    {
        $this->namePrefix = $namePrefix;

        return $this;
    }

    // The prefix first, so a downloaded file says what it is before naming its book
    public function getVichMediaPath(): string
    {
        return self::MEDIA_DIRECTORY . '/printables/' . ('' !== $this->namePrefix ? $this->namePrefix . '-' : '') . ($this->book?->getSlug() ?? 'temp');
    }
}
