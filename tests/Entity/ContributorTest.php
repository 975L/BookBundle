<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Entity;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Serie;
use PHPUnit\Framework\TestCase;

class ContributorTest extends TestCase
{
    // What a template printing "{{ book.author }}" gets: the very string the column held before the person became a row of their own
    public function testAContributorPrintsAsTheirName(): void
    {
        $this->assertSame('Tim Loval', (string) new Contributor()->setName('Tim Loval'));
    }

    // The whole point of one entity instead of two: a book both written and drawn by the same person is listed once on their page, not twice
    public function testABookBothWrittenAndDrawnIsListedOnce(): void
    {
        $book = new Book();
        $contributor = new Contributor();
        $contributor->getAuthoredBooks()->add($book);
        $contributor->getIllustratedBooks()->add($book);

        $this->assertSame([$book], $contributor->getBooks());
    }

    // Both hats, on two different books: the page lists what they wrote and what they drew alike
    public function testWhatTheyWroteAndWhatTheyDrewAreReadTogether(): void
    {
        $written = new Book();
        $drawn = new Book();
        $contributor = new Contributor();
        $contributor->getAuthoredBooks()->add($written);
        $contributor->getIllustratedBooks()->add($drawn);

        $this->assertCount(2, $contributor->getBooks());
        $this->assertCount(0, $contributor->getSeries());
    }

    // The guard the "Masqué" switch reads: setting aside someone a shown book credits would leave that book naming a page answering 404 (see ContributorCrudController::updateEntity)
    public function testSomeoneCreditedOnAShownBookHoldsVisibleContent(): void
    {
        $contributor = new Contributor();
        $contributor->getAuthoredBooks()->add(new Book());

        $this->assertTrue($contributor->holdsVisibleContent());
    }

    // Set aside or trashed, what credits them is off the site too and holds them back from nothing - unlike holdsContent(), which the trash reads and which counts every row
    public function testWhatIsAlreadyOffTheSiteHoldsThemBackFromNothing(): void
    {
        $contributor = new Contributor();
        $contributor->getAuthoredBooks()->add(new Book()->setHidden(true));
        $contributor->getIllustratedSeries()->add(new Serie()->setIsDeleted(true));

        $this->assertTrue($contributor->holdsContent());
        $this->assertFalse($contributor->holdsVisibleContent());
    }

    // Credited nowhere, they go to the trash like any other row
    public function testSomeoneCreditedNowhereHoldsNothing(): void
    {
        $contributor = new Contributor();

        $this->assertFalse($contributor->holdsContent());
        $this->assertFalse($contributor->holdsVisibleContent());
    }

    // A serie credits them just as a book does, and refuses them the trash for the same reason
    public function testASerieCreditingThemHoldsThemBackToo(): void
    {
        $contributor = new Contributor();
        $contributor->getAuthoredSeries()->add(new Serie());

        $this->assertTrue($contributor->holdsContent());
        $this->assertTrue($contributor->holdsVisibleContent());
    }

    // The kind carried by each row is what tells the portrait from the backdrop, as it does on a serie (see Serie::getCovers())
    public function testTheKindSortsThePortraitFromTheBackground(): void
    {
        $contributor = new Contributor();
        $contributor->addPortrait(new \c975L\BookBundle\Entity\ContributorMedia());
        $contributor->addBackground(new \c975L\BookBundle\Entity\ContributorMedia());

        $this->assertCount(1, $contributor->getPortraits());
        $this->assertCount(1, $contributor->getBackgrounds());
        $this->assertCount(2, $contributor->getMedias());
    }
}
