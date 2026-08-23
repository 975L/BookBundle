<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Twig;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Twig\BookTranslationExtension;
use PHPUnit\Framework\TestCase;

// Book::getTranslation() walks the children of a book alone, so it answers from the original and answers nothing from a translation - the language switch was there on the French page and gone from the English one
class BookTranslationExtensionTest extends TestCase
{
    public function testTheOriginalOffersItsTranslations(): void
    {
        [$original, $english, $spanish] = self::family();

        $this->assertSame([$english, $spanish], BookTranslationExtension::translations($original));
    }

    // The end the visitor arrived at is not the end the family is stored from
    public function testATranslationOffersTheOriginalAndItsSiblings(): void
    {
        [$original, $english, $spanish] = self::family();

        $this->assertSame([$original, $spanish], BookTranslationExtension::translations($english));
    }

    // A book alone in its language has nowhere to send anyone, and the button is absent rather than disabled
    public function testABookWithNoTranslationOffersNone(): void
    {
        $this->assertSame([], BookTranslationExtension::translations(new Book()->setLanguage('fr')));
    }

    // A translation in the trash is off the site, and the switch would send the reader straight to its 410
    public function testATranslationInTheTrashIsNotOffered(): void
    {
        [$original, $english, $spanish] = self::family();
        $english->setIsDeleted(true);

        $this->assertSame([$spanish], BookTranslationExtension::translations($original));
    }

    // A language names itself, and one this bundle holds no word for prints as its code rather than as nothing
    public function testALanguageIsNamedByItsOwnEndonym(): void
    {
        $this->assertSame('Español', BookTranslationExtension::languageLabel('es'));
        $this->assertSame('Deutsch', BookTranslationExtension::languageLabel('de'));
        $this->assertSame('sv', BookTranslationExtension::languageLabel('sv'));
        $this->assertSame('', BookTranslationExtension::languageLabel(null));
    }

    /** @return array{Book, Book, Book} */
    private static function family(): array
    {
        $original = new Book()->setLanguage('fr');
        $english = new Book()->setLanguage('en')->setTranslationBook($original);
        $spanish = new Book()->setLanguage('es')->setTranslationBook($original);

        $original->addTranslation($english);
        $original->addTranslation($spanish);

        return [$original, $english, $spanish];
    }
}
