<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Service;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookCategory;
use c975L\BookBundle\Entity\Character;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Service\BookTranslator;
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\UiBundle\Service\ContentTranslator;
use PHPUnit\Framework\TestCase;

// What this catalog names its rows and which of their texts a translator writes - the map the purge listener, the language screens and the seeder all read
class BookTranslatorTest extends TestCase
{
    private function createTranslator(): BookTranslator
    {
        return new BookTranslator($this->createStub(ContentTranslator::class), new SiteLocales(['fr', 'en'], 'fr'));
    }

    public function testEachKindOfRowIsNamedApart(): void
    {
        $translator = $this->createTranslator();

        $named = [];
        foreach ([new Book(), new Serie(), new BookCategory(), new Strip(), new Character(), new Contributor()] as $row) {
            $named[$row::class] = $translator->owner($row);
        }

        $this->assertSame([
            Book::class => 'book_book',
            Serie::class => 'book_serie',
            BookCategory::class => 'book_category',
            Strip::class => 'book_strip',
            Character::class => 'book_character',
            Contributor::class => 'book_contributor',
        ], $named);

        // Two rows sharing an owner type would share their translations the day their ids met
        $this->assertCount(6, array_unique($named));
    }

    // The first field of each list is the row's own name: it is what a listing, a card and a <title> read, and what translatedLocales() asks before saying a language says anything at all
    public function testTheFirstFieldOfEachListIsTheRowsOwnName(): void
    {
        $translator = $this->createTranslator();

        $this->assertSame('title', $translator->fields(new Book())[0]);
        $this->assertSame('title', $translator->fields(new Serie())[0]);
        $this->assertSame('title', $translator->fields(new BookCategory())[0]);
        $this->assertSame('title', $translator->fields(new Strip())[0]);
        $this->assertSame('name', $translator->fields(new Character())[0]);
    }

    // A person is not translated: rewriting an author's name is not translating, and the sample catalog already says so
    public function testAPersonsOwnNameIsNotTranslated(): void
    {
        $this->assertSame(['summary'], $this->createTranslator()->fields(new Contributor()));
    }

    // A character is: a translated book renames the people in it, and the page reading them has to follow
    public function testACharacterIsRenamedByTheTranslation(): void
    {
        $this->assertSame(['name', 'presentation'], $this->createTranslator()->fields(new Character()));
    }

    // Nothing is laid over a row a language says nothing of, the getters then falling back on the text the row was written in
    public function testARowKeepsItsOwnTextsUntilSomethingIsLaidOverThem(): void
    {
        $book = new Book()->setTitle('La maison du quai');

        $this->assertSame('La maison du quai', $book->getTitle());

        $book->setTranslated(['title' => 'The House on the Quay']);
        $this->assertSame('The House on the Quay', $book->getTitle());

        // What the row itself carries, whatever is laid over it - what a language screen offers as the thing to translate
        $this->assertSame('La maison du quai', $book->getUntranslated('title'));
    }
}
