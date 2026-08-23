<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Twig;

use c975L\BookBundle\Service\BookCatalogRegistry;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFunction;

// The name the catalog page carries, which the template wrote in French and neither a translation nor a site could take over
class BookCatalogExtension
{
    public function __construct(
        private readonly BookCatalogRegistry $catalogRegistry,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // The site's own word, translated in the "book" catalog when it holds one, printed as is otherwise - which allows "Les histoires" as well as "label.stories"
    #[AsTwigFunction('book_catalog_title')]
    public function title(): string
    {
        return $this->translator->trans($this->catalogRegistry->getTitle() ?? 'label.books', [], 'book');
    }
}
