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
use c975L\BookBundle\Entity\BookLink;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\ContributorLink;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

// A platform row left without its kind or its address is refused before the flush, where the database would otherwise answer with a 500
class PlatformLinkValidationTest extends TestCase
{
    /** @return iterable<string, array{class-string}> */
    public static function linkClasses(): iterable
    {
        yield 'book' => [BookLink::class];
        yield 'contributor' => [ContributorLink::class];
    }

    #[DataProvider('linkClasses')]
    public function testABlankRowIsRefusedOnItsKindAndItsUrl(string $class): void
    {
        $violations = $this->validator()->validate(new $class());

        $paths = [];
        foreach ($violations as $violation) {
            $paths[] = $violation->getPropertyPath();
        }
        sort($paths);

        $this->assertSame(['kind', 'url'], $paths);
    }

    #[DataProvider('linkClasses')]
    public function testAFilledRowIsAccepted(string $class): void
    {
        $link = new $class()->setKind('epub_fnac')->setUrl('https://www.fnac.com/ia1/Camille-Ferrand');

        $this->assertCount(0, $this->validator()->validate($link));
    }

    // Read off the attribute rather than by validating the owner, whose UniqueEntity asks for the Doctrine services a unit test has none of (see BookContributorTest)
    public function testTheOwnersValidateTheirLinks(): void
    {
        $this->assertCount(1, new \ReflectionProperty(Book::class, 'links')->getAttributes(Assert\Valid::class));
        $this->assertCount(1, new \ReflectionProperty(Contributor::class, 'links')->getAttributes(Assert\Valid::class));
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }
}
