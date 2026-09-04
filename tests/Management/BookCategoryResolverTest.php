<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Management;

use c975L\BookBundle\Entity\BookCategory;
use c975L\BookBundle\Management\BookCategoryResolver;
use c975L\BookBundle\Repository\BookCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

// The slugs a book names in an archive turned into the rows they stand for - what lets the books and their categories be imported in whichever order the archive lists them
class BookCategoryResolverTest extends TestCase
{
    public function testASlugNoCategoryCarriesYetBecomesANewRow(): void
    {
        $resolved = [];
        $persisted = [];

        $categories = $this->resolver($persisted)->resolveAll(['romans'], $resolved);

        $this->assertCount(1, $categories);
        $this->assertSame('romans', $categories[0]->getSlug());
        // The column is required, so the shell is named after the slug until the category's own kind writes over it
        $this->assertSame('romans', $categories[0]->getTitle());
        $this->assertSame($categories, $persisted);
    }

    public function testAnExistingSlugIsMatchedRatherThanDoubled(): void
    {
        $existing = new BookCategory()->setSlug('romans')->setTitle('Romans');
        $resolved = [];
        $persisted = [];

        $categories = $this->resolver($persisted, $existing)->resolveAll(['romans'], $resolved);

        $this->assertSame([$existing], $categories);
        $this->assertSame([], $persisted);
    }

    // findOneBy() can't see a row persisted but not yet flushed, so the run carries what it created: two books of the same absent category would otherwise each make their own
    public function testTheSameSlugTwiceInOneRunResolvesToOneRow(): void
    {
        $resolved = [];
        $persisted = [];
        $resolver = $this->resolver($persisted);

        $first = $resolver->resolveAll(['romans'], $resolved);
        $second = $resolver->resolveAll(['romans'], $resolved);

        $this->assertSame($first[0], $second[0]);
        $this->assertCount(1, $persisted);
    }

    // The list comes from an archive file and is typed by nobody: what is not a name is skipped rather than turned into a row
    public function testWhatIsNotASlugIsSkipped(): void
    {
        $resolved = [];
        $persisted = [];

        $categories = $this->resolver($persisted)->resolveAll(['', null, 12, ['romans']], $resolved);

        $this->assertSame([], $categories);
        $this->assertSame([], $persisted);
    }

    /** @param list<object> $persisted filled with everything the resolver hands to the entity manager */
    private function resolver(array &$persisted, ?BookCategory $existing = null): BookCategoryResolver
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $repository = $this->createStub(BookCategoryRepository::class);
        $repository->method('findOneBy')->willReturn($existing);

        return new BookCategoryResolver($em, $repository);
    }
}
