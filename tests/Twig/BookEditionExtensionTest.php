<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Twig;

use c975L\BookBundle\Entity\BookContributor;
use c975L\BookBundle\Entity\BookEdition;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use c975L\BookBundle\Twig\BookEditionExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

// A format and a part are both named in the site's own words, never as a case of an enum printed raw
class BookEditionExtensionTest extends TestCase
{
    private function extension(array $providers = []): BookEditionExtension
    {
        $translator = $this->createStub(TranslatorInterface::class);
        // The real one hands back any string it holds no entry for, which is what lets a plain label through
        $translator->method('trans')->willReturnArgument(0);

        return new BookEditionExtension(new BookCustomizationRegistry($providers, $translator), $translator);
    }

    public function testAnEditionIsNamedByItsKind(): void
    {
        $extension = $this->extension();

        $this->assertSame('label.edition_paper', $extension->label(new BookEdition()->setKind('paper')));
        $this->assertSame('label.edition_paper', $extension->label('paper'));
    }

    // A kind no vocabulary declares is printed as it stands rather than swallowed
    public function testAnUndeclaredKindIsPrintedAsItStands(): void
    {
        $this->assertSame('vinyle', $this->extension()->label('vinyle'));
    }

    // An edition opened and not yet given a format has nothing to say, and says nothing
    public function testAnEditionWithNoKindPrintsNothing(): void
    {
        $this->assertSame('', $this->extension()->label(new BookEdition()));
        $this->assertSame('', $this->extension()->label(null));
        $this->assertSame('', $this->extension()->label(''));
    }

    public function testAPartIsNamedByItsRole(): void
    {
        $extension = $this->extension();

        $this->assertSame('label.role_narrator', $extension->roleLabel(new BookContributor()->setRole('narrator')));
        $this->assertSame('label.role_narrator', $extension->roleLabel('narrator'));
    }

    // The two the book holds in columns of its own are named too, no vocabulary declaring them
    public function testTheAuthorAndTheIllustratorAreNamedAsWell(): void
    {
        $extension = $this->extension();

        $this->assertSame('label.author', $extension->roleLabel('author'));
        $this->assertSame('label.illustrator', $extension->roleLabel('illustrator'));
    }
}
