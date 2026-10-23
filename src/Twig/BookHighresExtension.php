<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Twig;

use c975L\BookBundle\Entity\Media;
use c975L\BookBundle\Entity\StripMedia;
use c975L\UiBundle\Service\ImageDimensionsReader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Attribute\AsTwigFunction;

// The high resolution a picture opens over the page (see c975LUi:Image:Zoom) or on a tablet's screen (see ListenJsonController), asked to the disk rather than to the entity: the name can be spelled for every media (see Media::getHighresFilename), including one uploaded before the derivatives were asked for, whose file would answer 404
class BookHighresExtension
{
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly ImageDimensionsReader $imageDimensionsReader,
    ) {
    }

    // The path to hand the zoom, or null for a media whose derivative was never produced, or produced no wider than the stored file - an upload at most that wide gives both the same pixels (see UiBundle's VichImageResizeListener, capping each at the upload's own width), and a second download of them shows nothing more
    public function highres(?Media $media): ?string
    {
        $filename = $media?->getHighresFilename();
        if (null === $filename) {
            return null;
        }

        $dimensions = $this->imageDimensionsReader->read($this->parameterBag->get('kernel.project_dir') . '/public/' . $filename);

        return null !== $dimensions && $dimensions['width'] > $media->getImageWidth() ? $filename : null;
    }

    #[AsTwigFunction('strip_highres')]
    public function stripHighres(?StripMedia $media): ?string
    {
        return $this->highres($media);
    }
}
