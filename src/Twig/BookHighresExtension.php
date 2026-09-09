<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Twig;

use c975L\BookBundle\Entity\StripMedia;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Attribute\AsTwigFunction;

// The high resolution a picture opens over the page (see c975LUi:Image:Zoom), for the medias that actually have one on disk
// The name is derived from the stored file's own (see StripMedia::getHighresFilename), so it can be spelled for every media - including one uploaded before this bundle asked for the derivatives, whose file was never produced. A template offering the zoom on that name would hand the dialog an address answering 404, which is why the disk is the thing asked rather than the entity
class BookHighresExtension
{
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    // The path to hand the zoom, or null for a media whose derivative was never produced - the caller then shows the picture without a zoom on it
    #[AsTwigFunction('strip_highres')]
    public function stripHighres(?StripMedia $media): ?string
    {
        $filename = $media?->getHighresFilename();

        if (null === $filename) {
            return null;
        }

        return is_file($this->parameterBag->get('kernel.project_dir') . '/public/' . $filename) ? $filename : null;
    }
}
