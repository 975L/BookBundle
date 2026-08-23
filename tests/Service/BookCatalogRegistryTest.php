<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Service;

use c975L\BookBundle\Contract\BookCatalogProviderInterface;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Service\BookCatalogRegistry;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;

// Which rows the catalog page lists is a decision, not a vocabulary: the first answer is the right one, and no answer at all hands the page back to the bundle
class BookCatalogRegistryTest extends TestCase
{
    // A site declaring nothing keeps exactly the page it had
    public function testASiteDeclaringNothingHandsThePageBackToTheBundle(): void
    {
        $registry = $this->registry([]);

        $this->assertNull($registry->getBooks());
        $this->assertNull($registry->getTitle());
    }

    // An empty list is an answer like any other: a catalog nothing has come out in yet is no undeclared catalog
    public function testAnEmptyListIsAnAnswerAndNotAnAbsentOne(): void
    {
        $this->assertSame([], $this->registry([self::provider([], null)])->getBooks());
    }

    public function testTheFirstProviderAnsweringIsTheOneRead(): void
    {
        $book = new Book();
        $registry = $this->registry([self::provider(null, null), self::provider([$book], 'Les histoires')]);

        $this->assertSame([$book], $registry->getBooks());
        $this->assertSame('Les histoires', $registry->getTitle());
    }

    // Renaming "Books" into "The stories" is a word, not a rule: it reads in the back office, without any site having to write a class for it
    public function testTheConfiguredTitleStandsWhenNoProviderNamesOne(): void
    {
        $this->assertSame('Les histoires', $this->registry([], 'Les histoires')->getTitle());
    }

    // An empty setting is no answer: the page keeps the bundle's own word
    public function testAnEmptyConfiguredTitleLeavesTheBundlesOwnWord(): void
    {
        $this->assertNull($this->registry([], '')->getTitle());
    }

    // The provider comes before the setting: a rule written in code wins over a word typed in
    public function testAProviderNamingTheCatalogWinsOverTheSetting(): void
    {
        $this->assertSame('Les albums', $this->registry([self::provider(null, 'Les albums')], 'Les histoires')->getTitle());
    }

    /** @param list<BookCatalogProviderInterface> $providers */
    private function registry(array $providers, ?string $configuredTitle = null): BookCatalogRegistry
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($configuredTitle);

        return new BookCatalogRegistry($providers, $configService);
    }

    /** @param list<Book>|null $books */
    private static function provider(?array $books, ?string $title): BookCatalogProviderInterface
    {
        return new readonly class ($books, $title) implements BookCatalogProviderInterface {
            /** @param list<Book>|null $books */
            public function __construct(private ?array $books, private ?string $title)
            {
            }

            #[\Override]
            public function getBooks(): ?array
            {
                return $this->books;
            }

            #[\Override]
            public function getTitle(): ?string
            {
                return $this->title;
            }
        };
    }
}
