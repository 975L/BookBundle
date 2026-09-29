<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Strip;
use c975L\ConfigBundle\Service\SiteLocales;

// Which languages each screen of the catalog really answers in - the one thing every localised url of this bundle is gated on (see LocalizedRouteNegotiator). The whole answer lives here rather than in five controllers, so translating the catalog is a change to this file and to nothing else
class BookTranslatedLocales
{
    public function __construct(
        private readonly SiteLocales $siteLocales,
        private readonly BookTranslator $bookTranslator,
    ) {
    }

    // An index answers in every language the site declares: everything on it but the books' own titles is this bundle's own interface
    /** @return list<string> */
    public function forIndex(): array
    {
        return $this->siteLocales->all();
    }

    // A row's page answers in every language the site declares, translated or not: "/en" is the language the catalog is read in, not a claim about the row - which BookTranslator::translatedLocales() answers
    /** @return list<string> */
    public function forEntry(): array
    {
        return $this->siteLocales->all();
    }

    // A planche answers only in the languages it was really written in: it is a line of dialogue, the whole of it being its text, so a French one under "/en" would be French content under an English address and hreflang
    /** @return list<string> */
    public function forStrip(Strip $strip): array
    {
        return $this->bookTranslator->translatedLocales($strip);
    }
}
