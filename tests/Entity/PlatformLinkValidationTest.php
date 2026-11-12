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
use c975L\BookBundle\Entity\BookVideo;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\ContributorLink;
use c975L\BookBundle\Entity\Media;
use c975L\BookBundle\Entity\Strip;
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

    // Printed in an href: an address pasted from a platform goes through, what a browser would run or open locally does not
    #[DataProvider('linkClasses')]
    public function testOnlyAnHttpAddressIsAccepted(string $class): void
    {
        foreach (['https://www.fnac.com/ia1/Camille-Ferrand?awc=a_b&x=1#y', 'http://example.com'] as $url) {
            $this->assertCount(0, $this->validator()->validateProperty(new $class()->setUrl($url), 'url'), $url);
        }

        foreach (['javascript:alert(1)', 'javascript://%0Aalert(1)', 'data:text/html,x', 'ftp://example.com/a', '/relative'] as $url) {
            $this->assertCount(1, $this->validator()->validateProperty(new $class()->setUrl($url), 'url'), $url);
        }
    }

    // Each of the address fields an editor pastes, the same way
    #[DataProvider('addressFields')]
    public function testAnAddressFieldTakesHttpAlone(object $entity, string $declaredOn, string $property): void
    {
        $reflection = new \ReflectionProperty($declaredOn, $property);

        $reflection->setValue($entity, 'https://example.com/a');
        $this->assertCount(0, $this->validator()->validateProperty($entity, $property));

        $reflection->setValue($entity, 'javascript://%0Aalert(1)');
        $this->assertCount(1, $this->validator()->validateProperty($entity, $property));

        $reflection->setValue($entity, null);
        $this->assertCount(0, $this->validator()->validateProperty($entity, $property));
    }

    /** @return iterable<string, array{object, class-string, string}> */
    public static function addressFields(): iterable
    {
        yield 'contributor website' => [new Contributor(), Contributor::class, 'website'];
        yield 'strip source' => [new Strip(), Strip::class, 'sourceUrl'];
        yield 'video' => [new BookVideo(), Media::class, 'youtubeUrl'];
        yield 'crowdfunding' => [new Book(), Book::class, 'crowdfunding'];
    }

    // Read off the attribute rather than by validating the owner, whose UniqueEntity asks for the Doctrine services a unit test has none of (see BookContributorTest)
    public function testTheOwnersValidateTheirLinks(): void
    {
        $this->assertCount(1, new \ReflectionProperty(Book::class, 'links')->getAttributes(Assert\Valid::class));
        $this->assertCount(1, new \ReflectionProperty(Contributor::class, 'links')->getAttributes(Assert\Valid::class));
    }

    // A volume number beyond its SMALLINT column, or below zero, is refused before the flush
    public function testAVolumeNumberStaysWithinItsColumn(): void
    {
        $this->assertCount(0, $this->validator()->validateProperty(new Book()->setNumber(12), 'number'));
        $this->assertCount(1, $this->validator()->validateProperty(new Book()->setNumber(40000), 'number'));
        $this->assertCount(1, $this->validator()->validateProperty(new Book()->setNumber(-1), 'number'));
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }
}
