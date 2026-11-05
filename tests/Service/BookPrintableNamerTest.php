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
use c975L\BookBundle\Entity\BookPrintable;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use c975L\BookBundle\Service\BookPrintableNamer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

// The file a reader downloads says what it is, in the book's language, and its level
class BookPrintableNamerTest extends TestCase
{
    // A registry answering the given "printables" section, with two levels, and a translator saying which locale it was asked in
    private function namer(array $section): BookPrintableNamer
    {
        $registry = $this->createStub(BookCustomizationRegistry::class);
        $registry->method('getSections')->willReturn([] === $section ? [] : ['printables' => $section]);
        $registry->method('getPrintableKinds')->willReturn(['beginner' => 'Beginners', 'advanced' => 'Advanced']);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $parameters, ?string $domain, ?string $locale): string => $id . ' ' . $locale . ' ' . $domain);

        return new BookPrintableNamer($registry, new AsciiSlugger(), $translator);
    }

    private static function printable(?string $kind = 'beginner', ?string $name = null): BookPrintable
    {
        $printable = new BookPrintable()->setBook(new Book()->setSlug('item-1')->setLanguage('en'));
        $printable->setKind($kind);
        $printable->setName($name);

        return $printable;
    }

    public function testThePrefixIsTheWordThenTheLevelInTheBooksLanguage(): void
    {
        $this->assertSame('activity-en-book-beginners-en-book', $this->namer(['filename' => 'Activity'])->prefix(self::printable()));
    }

    public function testThePrefixIsReadInTheDomainTheSectionNames(): void
    {
        $this->assertSame('activity-en-site-beginners-en-book', $this->namer(['filename' => 'Activity', 'domain' => 'site'])->prefix(self::printable()));
    }

    // A level the site no longer names is not written under its raw key
    public function testALevelTheSiteDoesNotNameIsLeftOut(): void
    {
        $this->assertSame('activity-en-book', $this->namer(['filename' => 'Activity'])->prefix(self::printable('retired')));
    }

    public function testASiteNamingNoFilenameGetsNoPrefix(): void
    {
        $this->assertSame('', $this->namer(['label' => 'Activities'])->prefix(self::printable()));
        $this->assertSame('', $this->namer([])->prefix(self::printable()));
    }

    // The unique part UiMediaNamer gave it stays, so two levels never collide
    public function testAStoredDocumentIsRenamedKeepingItsUniquePart(): void
    {
        $printable = self::printable(name: 'medias/book/printables/item-1-6aecced7ef358.pdf');

        $this->assertSame('medias/book/printables/activity-en-book-beginners-en-book-item-1-6aecced7ef358.pdf', $this->namer(['filename' => 'Activity'])->renamed($printable));
    }

    public function testADocumentAlreadyRightIsLeftAlone(): void
    {
        $printable = self::printable(name: 'medias/book/printables/activity-en-book-beginners-en-book-item-1-6aecced7ef358.pdf');

        $this->assertNull($this->namer(['filename' => 'Activity'])->renamed($printable));
    }

    public function testADocumentWithNoFileIsLeftAlone(): void
    {
        $this->assertNull($this->namer(['filename' => 'Activity'])->renamed(self::printable()));
    }
}
