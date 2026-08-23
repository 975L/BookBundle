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
use c975L\BookBundle\Twig\BookVersionExtension;
use PHPUnit\Framework\TestCase;

// The other versions of the same text, read from either end of the family - the cross link was missing on the old one's page when it was only read from the recent one
class BookVersionExtensionTest extends TestCase
{
    // A book nothing replaces and which replaces nothing has no other version to offer
    public function testABookAloneOffersNoOtherVersion(): void
    {
        $this->assertSame([], BookVersionExtension::versions(self::book('Chat et Chocolat')));
    }

    // From the current version: the one it replaces
    public function testTheCurrentVersionOffersTheOneItReplaces(): void
    {
        $current = self::book('Chat et Chocolat');
        $previous = self::book('Chat et Chocolat - Édition originale');
        $previous->setNewerVersion($current);

        $this->assertSame([$previous], BookVersionExtension::versions($current));
    }

    // The link is unique and reads from both ends: laying "replaced by" on one side lays "replaces" on the other, without waiting for Doctrine to reload both rows
    public function testTheLinkIsReadFromBothEndsAsSoonAsItIsSet(): void
    {
        $current = self::book('Chat et Chocolat');
        $previous = self::book('Chat et Chocolat - Édition originale');
        $previous->setNewerVersion($current);

        $this->assertSame($previous, $current->getPreviousVersion());
        $this->assertSame($current, $previous->getNewerVersion());

        // And the other way round, laying it from the current book
        $other = self::book('Chat et Chocolat - Texte seul');
        $current->setPreviousVersion($other);

        $this->assertSame($current, $other->getNewerVersion());
        // The version it replaced is freed: a book replaces one only
        $this->assertNull($previous->getNewerVersion());
    }

    // A version in the trash is off the site: offered as a link, it would send the reader straight onto its 410
    public function testATrashedVersionIsNotOffered(): void
    {
        $current = self::book('Chat et Chocolat');
        $previous = self::book('Chat et Chocolat - Édition originale')->setIsDeleted(true);
        $previous->setNewerVersion($current);

        $this->assertSame([], BookVersionExtension::versions($current));
    }

    // A third version chains onto the second: from the first, both read, the most recent first
    public function testAChainIsWalkedFromWhicheverEndTheVisitorArrivedAt(): void
    {
        $third = self::book('Chat et Chocolat');
        $second = self::book('Chat et Chocolat - 2e édition');
        $first = self::book('Chat et Chocolat - Édition originale');
        $second->setNewerVersion($third);
        $first->setNewerVersion($second);

        $this->assertSame([$third, $second], BookVersionExtension::versions($first));
        $this->assertSame([$second, $first], BookVersionExtension::versions($third));
        $this->assertSame([$third, $first], BookVersionExtension::versions($second));
    }

    private static function book(string $title): Book
    {
        return new Book()->setTitle($title);
    }
}
