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
use c975L\BookBundle\Enum\BookContributorRole;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BookContributorTest extends TestCase
{
    // What the back office writes on the collapsed row, a credit being read by the name of the person it names
    public function testACreditPrintsAsTheNameOfWhoItNames(): void
    {
        $credit = new BookContributor()->setContributor(new Contributor()->setName('Lily Mitchell'));

        $this->assertSame('Lily Mitchell', (string) $credit);
    }

    // What the sheet prints on its own line, and what the structured data names as translator: the people holding one part, in the order the back office laid them
    public function testTheBookReadsThePeopleHoldingOnePart(): void
    {
        $narrator = new Contributor()->setName('Lily Mitchell');
        $translator = new Contributor()->setName('Anna Stomp');
        $book = new Book();
        $book->addContributor(new BookContributor()->setContributor($narrator)->setRole(BookContributorRole::Narrator->value));
        $book->addContributor(new BookContributor()->setContributor($translator)->setRole(BookContributorRole::Translator->value));

        $this->assertSame([$narrator], $book->getContributorsOf('narrator'));
        $this->assertSame([$translator], $book->getContributorsOf('translator'));
        $this->assertSame([], $book->getContributorsOf('colourist'));
    }

    // The book's own side of the row, which is what the export, the copy and the version set aside are written through: the credit carries the book it belongs to
    public function testACreditAddedIsBoundToItsBook(): void
    {
        $book = new Book();
        $credit = new BookContributor()->setRole('narrator');
        $book->addContributor($credit);

        $this->assertSame($book, $credit->getBook());
        $this->assertCount(1, $book->getContributors());

        $book->removeContributor($credit);
        $this->assertCount(0, $book->getContributors());
    }

    // Neither author nor illustrator: those two are the book's own columns, picked on its screen, and a list offering them would have an editor credit the same person twice
    public function testTheDefaultRolesLeaveOutWhatTheBookHoldsInColumns(): void
    {
        $this->assertSame(['narrator', 'translator'], array_keys(BookContributorRole::defaults()));
        $this->assertSame('label.role_narrator', BookContributorRole::Narrator->label());
    }

    // The same person twice under the same part is what the book_contributor_role constraint refuses, and the database refusing it is a 500 with the whole screen lost: the message has to be raised before the flush. Read off the attribute rather than by validating the book, whose UniqueEntity asks for the Doctrine services a unit test has none of (see MediaTest)
    public function testTheSamePersonTwiceUnderTheSamePartIsRefused(): void
    {
        $person = new Contributor()->setName('Lily Mitchell');
        $book = new Book();
        $book->addContributor(new BookContributor()->setContributor($person)->setRole('narrator'));
        $book->addContributor(new BookContributor()->setContributor($person)->setRole('narrator'));

        $violations = $this->validator()->validate($book->getContributors(), $this->uniqueCredits());

        $this->assertCount(1, $violations);
        $this->assertSame('label.contributor_role_duplicate', $violations[0]->getMessageTemplate());
        // On the person field of the second row, which is where the back office prints it
        $this->assertSame('[1].contributor', $violations[0]->getPropertyPath());
    }

    // The same person under two parts is the whole point of the row - a voice reading one book and carrying another into Spanish - and the same part held by two people is a book read by two voices
    public function testTwoPartsOrTwoPeopleAreAccepted(): void
    {
        $person = new Contributor()->setName('Lily Mitchell');
        $other = new Contributor()->setName('Anna Stomp');
        $book = new Book();
        $book->addContributor(new BookContributor()->setContributor($person)->setRole('narrator'));
        $book->addContributor(new BookContributor()->setContributor($person)->setRole('translator'));
        $book->addContributor(new BookContributor()->setContributor($other)->setRole('narrator'));

        $this->assertCount(0, $this->validator()->validate($book->getContributors(), $this->uniqueCredits()));
    }

    private function uniqueCredits(): Assert\Unique
    {
        return new \ReflectionProperty(Book::class, 'contributors')->getAttributes(Assert\Unique::class)[0]->newInstance();
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->getValidator();
    }
}
