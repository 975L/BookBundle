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
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

// A drawing a child colours on screen, touch by touch, in an app or a page cutting it into zones (see ListenJsonController). Always a PNG of black lines on white, stored as sent: no resizing and no webp, whose blurred edges would move the zones apart. Its crayons come with it, read once off the coloured illustration it was drawn from (see BookColoringPalette), and its fingerprint tells a reader keeping a child's colours that the drawing under them was replaced
#[ORM\Entity]
#[Vich\Uploadable]
class BookColoring extends Media implements VichMediaNamableInterface
{
    use PublishedOnTrait;

    #[ORM\ManyToOne(targetEntity: Book::class, inversedBy: 'colorings')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Book $book = null;

    // Hex colours, "#rrggbb", the illustration's own then the black and greys every drawing gets
    /** @var list<string>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $palette = null;

    // A hash of the drawing's bytes, written on every upload (see BookColoringFingerprintListener)
    #[ORM\Column(length: 32, nullable: true)]
    #[Assert\Length(max: 32)]
    private ?string $fingerprint = null;

    // Checked on the row like a document to print's PDF: a photo or a webp here would leave no clean line to cut zones along
    #[Assert\Callback]
    public function validatePng(ExecutionContextInterface $context): void
    {
        if (null === $this->file || 'png' === $this->file->guessExtension()) {
            return;
        }

        $context->buildViolation('label.coloring_not_png')
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

    /** @return list<string> */
    public function getPalette(): array
    {
        return $this->palette ?? [];
    }

    /** @param array<int, string>|null $palette */
    public function setPalette(?array $palette): static
    {
        $this->palette = null === $palette ? null : array_values($palette);

        return $this;
    }

    public function getFingerprint(): ?string
    {
        return $this->fingerprint;
    }

    public function setFingerprint(?string $fingerprint): static
    {
        $this->fingerprint = $fingerprint;

        return $this;
    }

    public function getVichMediaPath(): string
    {
        return self::MEDIA_DIRECTORY . '/colorings/' . ($this->book?->getSlug() ?? 'temp');
    }
}
