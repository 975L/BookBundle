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
use Twig\Attribute\AsTwigFunction;

// The other languages one and the same book is written in. Book::getTranslation() only walks the children of a book, so it answers from the original and answers nothing from a translation - which is the language switch missing on every page but the first. The family is read from whichever end the visitor arrived at
class BookTranslationExtension
{
    // How a language names itself, never translated: a reader looking for their own language looks for the word they use for it, not for the word this book's language uses
    private const array ENDONYMS = [
        'de' => 'Deutsch',
        'en' => 'English',
        'es' => 'Español',
        'fr' => 'Français',
        'it' => 'Italiano',
        'nl' => 'Nederlands',
        'pt' => 'Português',
    ];

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

    // The endonym of a language, its own code when this bundle does not know the word - a code printed as it stands is what makes a missing entry visible rather than silent
    #[AsTwigFunction('book_language_label')]
    public static function languageLabel(?string $language): string
    {
        return self::ENDONYMS[(string) $language] ?? (string) $language;
    }
}
