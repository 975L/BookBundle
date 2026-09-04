<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Entity;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookCategory;
use PHPUnit\Framework\TestCase;

class BookCategoryTest extends TestCase
{
    // The owning side of the relation is the book, which is the screen a category is picked on: adding from either end has to leave both holding the other, or a category loaded in memory lists nothing of what was just filed under it
    public function testFilingABookUnderACategoryIsReadFromBothEnds(): void
    {
        $category = new BookCategory();
        $book = new Book();

        $category->addBook($book);

        $this->assertTrue($category->getBooks()->contains($book));
        $this->assertTrue($book->getCategories()->contains($category));
    }

    public function testTakingABookOutOfACategoryIsReadFromBothEnds(): void
    {
        $category = new BookCategory();
        $book = new Book();
        $category->addBook($book);

        $category->removeBook($book);

        $this->assertCount(0, $category->getBooks());
        $this->assertCount(0, $book->getCategories());
    }

    // Filing the same book twice files it once: the back office saves the whole collection at each edit, and a doubled row would print the category twice under the book
    public function testABookIsFiledUnderACategoryOnlyOnce(): void
    {
        $category = new BookCategory();
        $book = new Book();

        $category->addBook($book);
        $book->addCategory($category);

        $this->assertCount(1, $category->getBooks());
        $this->assertCount(1, $book->getCategories());
    }

    // What a book's page links to: a category set aside or in the trash answers 404, so its name reads as plain text there rather than as a broken link (see components/Book/Informations.html.twig)
    public function testABookOnlyShowsTheCategoriesTheSiteServes(): void
    {
        $book = new Book();
        $shown = new BookCategory()->setTitle('Romans');
        $book->addCategory($shown);
        $book->addCategory(new BookCategory()->setTitle('Brouillons')->setHidden(true));
        $book->addCategory(new BookCategory()->setTitle('Anciens')->setIsDeleted(true));

        $this->assertCount(3, $book->getCategories());
        $this->assertSame([$shown], array_values($book->getShownCategories()->toArray()));
    }
}
