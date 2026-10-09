<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Entity;

use c975L\BookBundle\Repository\BookSettingsRepository;
use c975L\UiBundle\Contract\HasBlocksInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Trait\HasBlocksTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

// The catalog's index has no entity of its own to hang its blocks on, the way a book, a serie and a category page have - this is that entity, and nothing else: a single row, holding what the editor composed above the listing (see ShopBundle's ShopSettings, which the shop's index is composed with)
#[ORM\Entity(repositoryClass: BookSettingsRepository::class)]
#[ORM\Table(name: 'book_settings')]
class BookSettings implements \Stringable, HasBlocksInterface
{
    use HasBlocksTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // The catalog's own line, said once above everything the blocks compose below it - left empty, the page falls back to the sentence it has always printed there
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $intro = null;

    // What the catalog's index says above its listing - composed in the back office with UiBundle's kinds, the same way a book, a serie and a category page are. The id breaks the ties, a position being typed in the back office and two blocks free to share one
    #[ORM\ManyToMany(targetEntity: Block::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'book_settings_block')]
    #[ORM\OrderBy(['position' => \SortDirection::Ascending, 'id' => \SortDirection::Ascending])]
    private Collection $blocks;

    public function __construct()
    {
        $this->blocks = new ArrayCollection();
    }

    // Never read by a visitor, the row having no name of its own: what EasyAdmin writes in its breadcrumb and its flash messages
    public function __toString(): string
    {
        return 'books';
    }

    // What this row says in the language being rendered, laid over the texts below and stored nowhere on the row: unmapped on purpose, Doctrine computing its changeset from the mapped properties and never from these getters, so a screen rendered in English cannot write English over the text the row was written in (see BookTranslator, the only thing that sets it)
    /** @var array<string, string|null>|null */
    private ?array $translated = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    // The language being rendered first, the text the row was written with otherwise
    public function getIntro(): ?string
    {
        return $this->translated['intro'] ?? $this->intro;
    }

    public function setIntro(?string $intro): static
    {
        $this->intro = $intro;

        return $this;
    }

    // Lays what a language says over the texts this row was written with, for the render being built and no longer than that - only BookTranslator calls it, and only on the front, a form screen having to go on reading the row
    /** @param array<string, string|null> $values field => value */
    public function setTranslated(array $values): void
    {
        $this->translated = $values;
    }

    // The text the row itself carries, whatever language is being rendered - what a language screen offers as the thing to translate, and what tells an untouched field from a written one (see BookTranslator)
    public function getUntranslated(string $field): ?string
    {
        return match ($field) {
            'intro' => $this->intro,
            default => null,
        };
    }
}
