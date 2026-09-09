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
use c975L\BookBundle\Entity\BookPresse;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use Doctrine\ORM\Mapping\OneToMany;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;

class OrphanRemovalCollectionSetterTest extends TestCase
{
    private const array WRITE_OPTIONS = [
        'enable_getter_setter_extraction' => true,
        'enable_adder_remover_extraction' => true,
    ];

    /** @return list<array{0: class-string}> */
    public static function entities(): array
    {
        return [[Book::class], [Serie::class], [Strip::class], [Contributor::class]];
    }

    /** @return list<string> the properties of an entity mapped as a collection Doctrine empties on removal */
    private function orphanRemovalCollections(\ReflectionClass $reflection): array
    {
        $properties = [];
        foreach ($reflection->getProperties() as $property) {
            foreach ($property->getAttributes(OneToMany::class) as $attribute) {
                if (true === ($attribute->newInstance()->orphanRemoval ?? false)) {
                    $properties[] = $property->getName();
                }
            }
        }

        return $properties;
    }

    // A form writes a collection through whatever PropertyAccessor finds for it, and finds nothing at all when the
    // adder is named after a singular its inflector doesn't produce - "presses" is singularized into "press", never
    // into "presse". Saving the book then raises, which is what dropping this entity's own setPresses() did
    /**
     * @param class-string $entity
     */
    #[DataProvider('entities')]
    public function testEveryOrphanRemovalCollectionCanBeWritten(string $entity): void
    {
        $extractor = new ReflectionExtractor();
        $reflection = new \ReflectionClass($entity);

        foreach ($this->orphanRemovalCollections($reflection) as $property) {
            $write = $extractor->getWriteInfo($entity, $property, self::WRITE_OPTIONS);

            $this->assertNotNull($write, $reflection->getShortName() . '::$' . $property . ' has no writer at all');
            $this->assertNotSame('none', $write->getType(), $reflection->getShortName() . '::$' . $property . ' has no writer a form can use: name its adder and its remover after the singular Symfony builds, or give it a setter that merges');
        }
    }

    // The setter such a collection falls back to must merge, never replace: Doctrine reads a replaced collection as
    // every row of it removed, and orphanRemoval deletes them - which emptied a book's press file on each save
    public function testSettingASubsetOfThePressesRemovesOnlyWhatIsMissing(): void
    {
        $book = new Book();
        $kept = new BookPresse();
        $dropped = new BookPresse();
        $book->addPresse($kept);
        $book->addPresse($dropped);
        $collection = $book->getPresses();

        $book->setPresses(new \Doctrine\Common\Collections\ArrayCollection([$kept]));

        $this->assertSame($collection, $book->getPresses(), 'The collection Doctrine watches must be the very one, not a new one put in its place');
        $this->assertTrue($book->getPresses()->contains($kept));
        $this->assertFalse($book->getPresses()->contains($dropped));
        $this->assertNull($dropped->getBook(), 'What is dropped is detached from its book, so orphanRemoval deletes that row and no other');
    }

    // What the form hands back untouched leaves the collection as it was, rather than every row being removed and added anew
    public function testSettingTheSamePressesChangesNothing(): void
    {
        $book = new Book();
        $presse = new BookPresse();
        $book->addPresse($presse);

        $book->setPresses(new \Doctrine\Common\Collections\ArrayCollection([$presse]));

        $this->assertCount(1, $book->getPresses());
        $this->assertSame($book, $presse->getBook());
    }
}
