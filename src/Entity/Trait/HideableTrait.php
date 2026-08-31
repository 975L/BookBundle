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

// The one column hiding a row takes, shared by the rows that carry one - the same way TrashableTrait carries the trash. A hidden row is off the site as long as the box is ticked: no list shows it, its page answers 404 and the sitemap stops declaring it at its next run - and nothing of it is touched, so a serie set aside mid-composition comes back whole with one click
// Deliberately not the trash: a trashed row is on its way out and says so with a 410, where a hidden one is simply not shown yet, and neither is what the other means
trait HideableTrait
{
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $hidden = false;

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): static
    {
        $this->hidden = $hidden;

        return $this;
    }
}
