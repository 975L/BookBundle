<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Entity;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookContributor;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\ContributorLink;
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

    // A voice reading a story is credited on it and nowhere else: their page would stand empty if the credits were not read with the two columns
    public function testWhatTheyOnlyNarratedIsReadWithWhatTheySigned(): void
    {
        $narrated = new Book();
        $contributor = new Contributor();
        $contributor->getCredits()->add(new BookContributor()->setBook($narrated)->setContributor($contributor)->setRole('narrator'));

        $this->assertSame([$narrated], $contributor->getBooks());
        $this->assertTrue($contributor->holdsContent());
    }

    // A book set aside, trashed or replaced left the site: it leaves their page too, which the joins fetching the two columns already do and a credit's own book does not (see Contributor::getBooks())
    public function testABookOffTheSiteLeavesThePageOfWhoeverNarratedIt(): void
    {
        $hidden = new Book()->setHidden(true);
        $contributor = new Contributor();
        $contributor->getCredits()->add(new BookContributor()->setBook($hidden)->setContributor($contributor)->setRole('narrator'));

        $this->assertSame([], $contributor->getBooks());
        // The trash still counts it: the row names them just as much, and is what the foreign key would trip on
        $this->assertTrue($contributor->holdsContent());
        $this->assertFalse($contributor->holdsVisibleContent());
    }

    // What their page and their card say under their name: nobody carries the word on their row, it is read from what credits them
    public function testTheirPartsAreReadFromWhatCreditsThem(): void
    {
        $contributor = new Contributor();
        $contributor->getIllustratedBooks()->add(new Book());
        $contributor->getCredits()->add(new BookContributor()->setBook(new Book())->setContributor($contributor)->setRole('narrator'));
        // The same part twice says it once: two books read by the same voice do not make two narrators
        $contributor->getCredits()->add(new BookContributor()->setBook(new Book())->setContributor($contributor)->setRole('narrator'));

        $this->assertSame(['illustrator', 'narrator'], $contributor->getRoles());
        $this->assertSame([], new Contributor()->getRoles());
    }

    // The word under their name answers to the same rule as the list it stands above (see getBooks()): someone whose only signed book is in the trash is called nothing, rather than "author" over an empty page
    public function testWhatIsOffTheSiteNamesNoPart(): void
    {
        $contributor = new Contributor();
        $contributor->getAuthoredBooks()->add(new Book()->setIsDeleted(true));
        $contributor->getIllustratedSeries()->add(new Serie()->setHidden(true));
        $contributor->getCredits()->add(new BookContributor()->setBook(new Book()->setHidden(true))->setContributor($contributor)->setRole('narrator'));

        $this->assertSame([], $contributor->getRoles());
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

    // Where their books are bought travels with them: the row knows the person it was added to, and lets go of them when it is taken off
    public function testAPlatformRowKnowsThePersonItWasAddedTo(): void
    {
        $contributor = new Contributor();
        $link = new ContributorLink()->setKind('epub_fnac')->setUrl('https://www.fnac.com/ia1/Camille-Ferrand');

        $contributor->addLink($link);
        $this->assertSame($contributor, $link->getContributor());
        $this->assertCount(1, $contributor->getLinks());

        // Added twice, the same row stays one: the collection is what the back office writes into
        $contributor->addLink($link);
        $this->assertCount(1, $contributor->getLinks());

        $contributor->removeLink($link);
        $this->assertCount(0, $contributor->getLinks());
        $this->assertNull($link->getContributor());
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
