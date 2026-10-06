<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Entity;

use c975L\BookBundle\Entity\Book;
use PHPUnit\Framework\TestCase;

// The one reading behind the "à paraître" wording, the catalog's listing of what is coming and the release alert
class BookPublicationTest extends TestCase
{
    public function testABookWithoutADateIsStillToCome(): void
    {
        $this->assertTrue(new Book()->isToBePublished());
    }

    public function testABookDatedTomorrowIsStillToCome(): void
    {
        $this->assertTrue(new Book()->setPublished(new \DateTime('+1 day'))->isToBePublished());
    }

    // Compared day by day and not to the current instant: a book dated today is out, whatever the hour the question is asked at
    public function testABookDatedTodayIsOut(): void
    {
        $this->assertFalse(new Book()->setPublished(new \DateTime('today'))->isToBePublished());
        $this->assertFalse(new Book()->setPublished(new \DateTime('-1 day'))->isToBePublished());
    }

    // The last of the chain of versions, the book itself when nothing replaces it
    public function testTheLatestVersionIsTheEndOfTheChain(): void
    {
        $first = new Book();
        $second = new Book();
        $third = new Book();
        $first->setNewerVersion($second);
        $second->setNewerVersion($third);

        $this->assertSame($third, $first->getLatestVersion());
        $this->assertSame($third, $third->getLatestVersion());
    }
}
