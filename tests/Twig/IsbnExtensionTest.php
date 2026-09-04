<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Twig;

use c975L\BookBundle\Twig\IsbnExtension;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;

class IsbnExtensionTest extends TestCase
{
    // The roots as a publisher declares them in the back office: one per line, hyphenated as they are printed
    private const string ROOTS = "978-2-9598005\n978-2-488750\n979-10-97666";

    private function extension(string $roots = self::ROOTS): IsbnExtension
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $key): mixed => 'book-isbn-prefixes' === $key ? $roots : null);

        return new IsbnExtension($configService);
    }

    // The very reason the roots are declared: two registrants of the publisher's own, cut at a different digit under the same prefix
    public function testEachRootIsCutWhereItEnds(): void
    {
        $extension = $this->extension();

        $this->assertSame('978-2-488750-24-0', $extension->isbnFilter('9782488750240'));
        $this->assertSame('978-2-9598005-0-4', $extension->isbnFilter('9782959800504'));
        $this->assertSame('979-10-97666-10-1', $extension->isbnFilter('9791097666101'));
    }

    // Hyphens laid in the wrong place read as another publisher's number, so a number the site declares no root for keeps its digits
    public function testANumberMatchingNoRootKeepsItsDigits(): void
    {
        $this->assertSame('9780306406157', $this->extension()->isbnFilter('9780306406157'));
        $this->assertSame('9782488750240', $this->extension('')->isbnFilter('9782488750240'));
    }

    // Two roots matching the same number - one being the start of the other - and the longest is the one actually naming it
    public function testTheLongestMatchingRootWins(): void
    {
        $extension = $this->extension("978-2\n978-2-488750");

        $this->assertSame('978-2-488750-24-0', $extension->isbnFilter('9782488750240'));
    }

    // A root leaving no room for a publication and a check digit names another number, not this one
    public function testARootAsLongAsTheNumberIsDeclined(): void
    {
        $extension = $this->extension('978-2-48875024-0');

        $this->assertSame('9782488750240', $extension->isbnFilter('9782488750240'));
    }

    // An ISBN typed with its hyphens, or stored as an integer, reads the same as the bare digits the entity holds
    public function testTheNumberIsReadThroughItsDigitsAlone(): void
    {
        $extension = $this->extension();

        $this->assertSame('978-2-488750-24-0', $extension->isbnFilter('978-2-488750-24-0'));
        $this->assertSame('978-2-488750-24-0', $extension->isbnFilter(9782488750240));
    }
}
