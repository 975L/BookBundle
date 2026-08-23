<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Contract;

// Implemented by the three rows a back-office deletion only takes off the site - a serie, a book, a strip. What the trash needs of them and nothing else, so BookTrashManager works on the three without knowing which one it holds (see Entity\Trait\TrashableTrait for the implementation they all share)
interface TrashableInterface
{
    public function getId(): ?int;

    public function getSlug(): ?string;

    public function isDeleted(): bool;

    public function setIsDeleted(bool $isDeleted): static;

    public function setModification(\DateTimeInterface $modification): static;
}
