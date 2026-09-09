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
use c975L\UiBundle\Contract\VichMultiSizeImageInterface;
use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
class StripMedia extends Media implements VichMediaNamableInterface, VichMultiSizeImageInterface
{
    // The three sizes an upload leaves on disk (see UiBundle's VichImageResizeListener, which does the resizing): the stored file the page shows, the one the zoom opens over it, and the thumbnail a listing is drawn with
    // The medium is the measure a planche is read at on the page, and a panel cut from it is squarer than the page itself - one width holds both. The highres is capped at what the source actually carries, so a scan of a printed page gives all it has and a panel exported at 600 stays at 600
    public const int MEDIUM_WIDTH = 600;
    public const int HIGHRES_WIDTH = 1600;
    public const int THUMBNAIL_SIZE = 300;

    #[ORM\ManyToOne(targetEntity: Strip::class, inversedBy: 'medias')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Strip $strip = null;

    public function getStrip(): ?Strip
    {
        return $this->strip;
    }

    public function setStrip(?Strip $strip): static
    {
        $this->strip = $strip;

        return $this;
    }

    // The kind only counts where a planche tells several apart: without it the path would open on a dash, which the file would then carry for good
    public function getVichMediaPath(): string
    {
        $kind = (string) $this->getKind();

        return self::MEDIA_DIRECTORY . '/strips/' . ('' !== $kind ? $kind . '-' : '') . ($this->strip?->getSlug() ?? 'temp');
    }

    public function getImageWidth(): int
    {
        return self::MEDIUM_WIDTH;
    }

    public function getThumbnailSize(): int
    {
        return self::THUMBNAIL_SIZE;
    }

    public function getHighresWidth(): int
    {
        return self::HIGHRES_WIDTH;
    }

    // Read off the stored name rather than held in a column of its own: the listener writes the derivatives beside the file under the very same name, and a column would be a second place for the same truth. Null for a media carrying no file, and for one uploaded before this bundle asked for the derivatives - a caller checks it before offering the zoom
    public function getThumbnailFilename(): ?string
    {
        return $this->deriveFilename('-thumb');
    }

    public function getHighresFilename(): ?string
    {
        return $this->deriveFilename('-highres');
    }

    // The sibling the listener saved next to the stored file, whose suffix comes before the extension
    private function deriveFilename(string $suffix): ?string
    {
        $name = $this->getName();

        return null === $name ? null : preg_replace('/(\.[^.\/]+)$/', $suffix . '$1', $name);
    }
}
