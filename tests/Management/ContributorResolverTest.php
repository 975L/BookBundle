<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Management;

use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Management\ContributorResolver;
use c975L\BookBundle\Repository\ContributorRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

// The name an archive carries turned into the row it stands for - what lets an export written before this entity existed be imported unchanged
class ContributorResolverTest extends TestCase
{
    public function testANameNobodyCarriesYetBecomesANewRow(): void
    {
        $resolved = [];
        $persisted = [];

        $contributor = $this->resolver($persisted)->resolve('Tim Loval', null, $resolved);

        $this->assertInstanceOf(Contributor::class, $contributor);
        $this->assertSame('Tim Loval', $contributor->getName());
        $this->assertSame('tim-loval', $contributor->getSlug());
        $this->assertSame([$contributor], $persisted);
    }

    // Matched by name, which is the only key the four string columns - and any archive written against them - ever held
    public function testAnExistingNameIsMatchedRatherThanDoubled(): void
    {
        $existing = new Contributor()->setName('Tim Loval')->setSlug('tim-loval');
        $resolved = [];
        $persisted = [];

        $contributor = $this->resolver($persisted, $existing)->resolve('Tim Loval', null, $resolved);

        $this->assertSame($existing, $contributor);
        $this->assertSame([], $persisted);
    }

    // findOneBy() can't see a row persisted but not yet flushed, so the run carries what it created: two books of the same absent author would otherwise each make their own
    public function testTheSameNameTwiceInOneRunResolvesToOneRow(): void
    {
        $resolved = [];
        $persisted = [];
        $resolver = $this->resolver($persisted);

        $first = $resolver->resolve('Tim Loval', null, $resolved);
        $second = $resolver->resolve('Tim Loval', null, $resolved);

        $this->assertSame($first, $second);
        $this->assertCount(1, $persisted);
    }

    // A book crediting nobody stays a book crediting nobody, the relation being nullable
    public function testABlankNameResolvesToNothing(): void
    {
        $resolved = [];
        $persisted = [];
        $resolver = $this->resolver($persisted);

        $this->assertNull($resolver->resolve(null, null, $resolved));
        $this->assertNull($resolver->resolve('   ', null, $resolved));
        $this->assertSame([], $persisted);
    }

    // The site travels with the name it was exported beside, and lands on the person - which is where it now belongs
    public function testTheWebsiteOfTheArchiveFillsAnEmptyOne(): void
    {
        $resolved = [];
        $persisted = [];

        $contributor = $this->resolver($persisted)->resolve('Tim Loval', ' https://example.org/tim ', $resolved);

        $this->assertSame('https://example.org/tim', $contributor?->getWebsite());
    }

    // Filled in, never overwritten: an archive naming no site must not erase the one the person carries, and one naming another says nothing the editor asked for
    public function testTheWebsiteAlreadyHeldIsLeftAlone(): void
    {
        $existing = new Contributor()->setName('Tim Loval')->setSlug('tim-loval')->setWebsite('https://example.org/original');
        $resolved = [];
        $persisted = [];

        $this->resolver($persisted, $existing)->resolve('Tim Loval', 'https://example.org/autre', $resolved);

        $this->assertSame('https://example.org/original', $existing->getWebsite());
    }

    // Two names slugifying alike are two people, so the second takes a slug of its own - the first is persisted but not yet flushed, so only the run's own memory knows it is taken
    public function testTwoNamesSlugifyingAlikeInOneRunDoNotShareASlug(): void
    {
        $resolved = [];
        $persisted = [];
        $resolver = $this->resolver($persisted);

        $first = $resolver->resolve('Jean-Luc Picard', null, $resolved);
        $second = $resolver->resolve('Jean Luc Picard', null, $resolved);

        $this->assertSame('jean-luc-picard', $first?->getSlug());
        $this->assertSame('jean-luc-picard-2', $second?->getSlug());
        $this->assertCount(2, $persisted);
    }

    // Two people called the same are two pages, so the slug is suffixed until nobody else holds it
    public function testASlugAlreadyTakenIsSuffixed(): void
    {
        $resolved = [];
        $persisted = [];
        $repository = $this->createStub(ContributorRepository::class);
        $repository->method('findOneByName')->willReturn(null);
        // The first two candidates are taken, the third is free
        $repository->method('findOneBy')->willReturnOnConsecutiveCalls(new Contributor(), new Contributor(), null);

        $contributor = $this->resolver($persisted, repository: $repository)->resolve('Tim Loval', null, $resolved);

        $this->assertSame('tim-loval-3', $contributor?->getSlug());
    }

    /** @param list<object> $persisted filled with everything the resolver hands to the entity manager */
    private function resolver(array &$persisted, ?Contributor $existing = null, ?ContributorRepository $repository = null): ContributorResolver
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        if (null === $repository) {
            $repository = $this->createStub(ContributorRepository::class);
            $repository->method('findOneByName')->willReturn($existing);
            $repository->method('findOneBy')->willReturn(null);
        }

        return new ContributorResolver($em, $repository, new AsciiSlugger());
    }
}
