<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Twig;

use c975L\BookBundle\Entity\Book;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Intl\Languages;
use Twig\Attribute\AsTwigFunction;

// The other languages one and the same book is written in. Book::getTranslation() only walks the children of a book, so it answers from the original and answers nothing from a translation - which is the language switch missing on every page but the first. The family is read from whichever end the visitor arrived at
class BookTranslationExtension
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    // The language this bundle's own words are read in: the one the url reads ("/es/livre/..."), the row's own language where there is none - which is every url of a single-language site
    #[AsTwigFunction('book_ui_locale')]
    public function uiLocale(?string $rowLanguage = null): ?string
    {
        $locale = $this->requestStack->getCurrentRequest()?->attributes->get('_locale');

        return \is_string($locale) && '' !== $locale ? $locale : $rowLanguage;
    }

    // Every other book of the family, whichever end of it this one sits at: the original and its siblings seen from a translation, the translations seen from the original
    /** @return list<Book> */
    #[AsTwigFunction('book_translations')]
    public static function translations(Book $book): array
    {
        $original = $book->getTranslationBook() ?? $book;
        $family = [$original, ...$original->getTranslations()];

        // A book in the trash is off the site: offered as a language, the switch would send the reader straight to its 410
        return array_values(array_filter(
            $family,
            static fn (Book $other): bool => $other !== $book && !$other->isDeleted() && null !== $other->getLanguage()
        ));
    }

    // A language named in the language the page is read in - "Anglais" on a French page, "French" on an English one - so a label never switches language mid-page
    #[AsTwigFunction('book_language_label')]
    public function languageLabel(?string $language): string
    {
        return self::languageName($language, $this->requestStack->getCurrentRequest()?->getLocale() ?? \Locale::getDefault());
    }

    // The name of a language in another one, capitalised as a label is, and its own code when Intl does not know it - a code printed as it stands is what makes a wrong value visible rather than silent
    public static function languageName(?string $language, string $displayLocale): string
    {
        $language = (string) $language;
        if (!Languages::exists($language)) {
            return $language;
        }

        $name = Languages::getName($language, $displayLocale);

        return mb_strtoupper(mb_substr($name, 0, 1)) . mb_substr($name, 1);
    }
}
