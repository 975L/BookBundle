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
use c975L\BookBundle\Entity\BookCategory;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Tests\BookPublicUrlGeneratorTestTrait;
use c975L\BookBundle\Twig\BookUrlExtension;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;

// What the layout writes as "alternate hreflang" links: a book and its translations are the same page in several languages, and without those links a search engine reads them as several pages saying the same thing
class BookUrlExtensionTest extends TestCase
{
    use BookPublicUrlGeneratorTestTrait;

    // Every language of the family, the book's own first: a group is read as a whole, and a page leaving itself out of it is not part of the group it declares
    public function testATranslatedBookDeclaresTheWholeFamily(): void
    {
        [$original, $english] = self::family();

        $this->assertSame([
            'fr' => 'https://example.com/livre/la-licorne',
            'en' => 'https://example.com/livre/the-unicorn',
            'es' => 'https://example.com/livre/el-unicornio',
        ], $this->createExtension()->alternates($original));

        // Read from the other end, the same group with the reader's own language leading it
        $this->assertSame([
            'en' => 'https://example.com/livre/the-unicorn',
            'fr' => 'https://example.com/livre/la-licorne',
            'es' => 'https://example.com/livre/el-unicornio',
        ], $this->createExtension()->alternates($english));
    }

    // A book no one has translated says nothing more than its canonical already does
    public function testAnUntranslatedBookDeclaresNothing(): void
    {
        $book = new Book()->setLanguage('fr')->setSlug('la-licorne');

        $this->assertSame([], $this->createExtension()->alternates($book));
    }

    // Absolute urls or none: a hreflang group is read from another site than the one serving it, and "site-url" is where the host comes from
    public function testTheFamilyIsNotDeclaredWithoutASiteUrl(): void
    {
        [$original] = self::family();

        $this->assertSame([], $this->createExtension('')->alternates($original));
    }

    // A family whose books are read elsewhere has no page here to send anyone to
    public function testTheFamilyIsNotDeclaredWithoutABookPrefix(): void
    {
        [$original] = self::family();

        $this->assertSame([], $this->createExtension('https://example.com', ['book-route-book' => ''])->alternates($original));
    }

    // The templates hand the person over rather than spelling their slug, the path for a link on the site and the absolute url for what is read off it
    public function testAPersonsPageIsGeneratedFromThePersonThemselves(): void
    {
        $contributor = new Contributor()->setName('Tim Loval')->setSlug('tim-loval');

        $this->assertSame('/auteur/tim-loval', $this->createExtension()->contributorPath($contributor));
        $this->assertSame('https://example.com/auteur/tim-loval', $this->createExtension()->contributorUrl($contributor));
    }

    // A site reading its people elsewhere serves that page from nowhere here: null rather than a throw, so a template asking for it prints no link instead of 500ing
    public function testAPersonsPageIsNotGeneratedWithoutAContributorPrefix(): void
    {
        $contributor = new Contributor()->setName('Tim Loval')->setSlug('tim-loval');
        $extension = $this->createExtension('https://example.com', ['book-route-contributor' => '']);

        $this->assertNull($extension->contributorPath($contributor));
        $this->assertNull($extension->contributorUrl($contributor));
    }

    // A site giving its categories a page of their own reaches them the same way, the templates handing the category over rather than spelling its slug
    public function testACategorysPageIsGeneratedFromTheCategoryItself(): void
    {
        $category = new BookCategory()->setTitle('Romans')->setSlug('romans');

        $this->assertSame('/categories/romans', $this->createExtension('https://example.com', ['book-route-categories' => 'categories'])->categoryPath($category));
    }

    // The default: a fresh install serves no category page, and the names then read as plain text on a book's page rather than as broken links
    public function testACategorysPageIsNotGeneratedWithoutACategoryPrefix(): void
    {
        $category = new BookCategory()->setTitle('Romans')->setSlug('romans');

        $this->assertNull($this->createExtension()->categoryPath($category));
    }

    private function createExtension(string $siteUrl = 'https://example.com', array $prefixes = []): BookUrlExtension
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        return new BookUrlExtension(new BookPublicUrlResolver($configService, $this->createRoutePrefix($prefixes), $this->createUrlGenerator()));
    }

    /** @return array{Book, Book, Book} */
    private static function family(): array
    {
        $original = new Book()->setLanguage('fr')->setSlug('la-licorne');
        $english = new Book()->setLanguage('en')->setSlug('the-unicorn')->setTranslationBook($original);
        $spanish = new Book()->setLanguage('es')->setSlug('el-unicornio')->setTranslationBook($original);

        $original->addTranslation($english);
        $original->addTranslation($spanish);

        return [$original, $english, $spanish];
    }
}
