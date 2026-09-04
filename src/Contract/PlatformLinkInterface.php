<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Contract;

// One stored platform address: the kind naming the platform in the site's own vocabulary (see BookCustomizationProviderInterface::getLinkKinds()) and the whole address that platform hands over. Implemented by every row carrying one - a book's and a person's - so the vocabulary, the twig functions and the templates read the two the same way (see Service\BookCustomizationRegistry and Twig\BookLinkExtension)
interface PlatformLinkInterface
{
    public function getKind(): ?string;

    public function getUrl(): ?string;
}
