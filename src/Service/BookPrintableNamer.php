<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\BookPrintable;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The words a document to print is stored under, so the file a reader downloads says what it is: the "filename" the site gives its "printables" section, then the document's level, both in the book's language (see BookCustomizationProviderInterface::getSections()). A site giving none keeps the book's slug alone
class BookPrintableNamer
{
    public function __construct(
        private readonly BookCustomizationRegistry $customizationRegistry,
        private readonly SluggerInterface $slugger,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // The slugged prefix, empty when the site names its documents by nothing
    public function prefix(BookPrintable $printable): string
    {
        $section = $this->customizationRegistry->getSections()['printables'] ?? [];
        $filename = \is_string($section['filename'] ?? null) ? $section['filename'] : '';
        if ('' === $filename) {
            return '';
        }

        $locale = $printable->getBook()?->getLanguage();
        $words = [$this->translator->trans($filename, [], \is_string($section['domain'] ?? null) ? $section['domain'] : 'book', $locale)];

        // A level the site no longer names is left out rather than written under its raw key
        $kinds = $this->customizationRegistry->getPrintableKinds();
        if (isset($kinds[(string) $printable->getKind()])) {
            $words[] = $this->translator->trans($kinds[(string) $printable->getKind()], [], 'book', $locale);
        }

        return strtolower($this->slugger->slug(implode(' ', $words))->toString());
    }

    // The name an already stored document should go by, keeping the unique part UiMediaNamer gave it, or null when it already does
    public function renamed(BookPrintable $printable): ?string
    {
        $name = (string) $printable->getName();
        $base = $printable->setNamePrefix($this->prefix($printable))->getVichMediaPath();
        $unique = 1 === preg_match('/-([0-9a-f]{13})\.pdf$/i', $name, $matches) ? $matches[1] : uniqid();
        $expected = $base . '-' . $unique . '.pdf';

        return '' === $name || $expected === $name ? null : $expected;
    }
}
