<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Twig;

use c975L\BookBundle\Contract\BookCustomizationProviderInterface;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookEdition;
use c975L\BookBundle\Entity\BookLink;
use c975L\BookBundle\Entity\BookMarketing;
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Entity\BookPresse;
use c975L\BookBundle\Entity\BookVideo;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use c975L\BookBundle\Twig\BookEditUrlExtension;
use c975L\BookBundle\Twig\BookSectionsExtension;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

// The anchor a section is reached by on the public page and the property it is written in are two names nothing else keeps in step - a section renamed on one side alone loses its pencil, or lands the editor on a field it has nothing to do with
class BookEditUrlExtensionTest extends TestCase
{
    // Every section of a book's page has somewhere to go, the sections holding files or platforms all leading to the editions the book is published in
    public function testEachSectionOfABookLeadsToTheFieldItIsWrittenIn(): void
    {
        $urls = $this->extension()->book($this->withId(new Book(), 7));

        $this->assertSame([
            'hero' => '/admin/7/title',
            'resume' => '/admin/7/summary',
            'apercu' => '/admin/7/videos',
            'extracts' => '/admin/7/editions',
            'editions' => '/admin/7/editions',
            'shops' => '/admin/7/editions',
            'podcasts' => '/admin/7/editions',
            'crowdfunding' => '/admin/7/crowdfunding',
            'presse' => '/admin/7/presses',
            'marketing' => '/admin/7/marketings',
            'informations' => '/admin/7/published',
        ], $urls);
    }

    public function testEachSectionOfAStripLeadsToTheFieldItIsWrittenIn(): void
    {
        $urls = $this->extension()->strip($this->withId(new Strip(), 3));

        $this->assertSame([
            'hero' => '/admin/3/title',
            'medias' => '/admin/3/medias',
            'summary' => '/admin/3/summary',
            'characters' => '/admin/3/characters',
            'sourceUrl' => '/admin/3/sourceUrl',
        ], $urls);
    }

    // A person's page opens on their name and carries the sentences they are introduced with, and nothing else of it is written on their own screen
    public function testEachSectionOfAPersonLeadsToTheFieldItIsWrittenIn(): void
    {
        $urls = $this->extension()->contributor($this->withId(new Contributor(), 5));

        $this->assertSame([
            'hero' => '/admin/5/name',
            'resume' => '/admin/5/summary',
        ], $urls);
    }

    // The card a person is reached by stands for the row entire, so it opens their screen rather than one of its fields
    public function testTheCardOfAPersonOpensTheirWholeScreen(): void
    {
        $this->assertSame('/admin/5', $this->extension()->contributorEditUrl($this->withId(new Contributor(), 5)));
        $this->assertNull($this->extension()->contributorEditUrl(new Contributor()));
    }

    // A serie's list of books is written on each book's own screen, so that section is deliberately left without a pencil rather than given one leading nowhere
    public function testASectionWrittenOnAnotherScreenGetsNoPencil(): void
    {
        $urls = $this->extension()->serie($this->withId(new Serie(), 2));

        $this->assertSame(['hero', 'resume'], array_keys($urls));
    }

    // Nothing to edit yet: an entity being rendered before it is saved has no edit screen to point at
    public function testAnEntityWithNoIdGetsNoPencil(): void
    {
        $this->assertSame([], $this->extension()->book(new Book()));
    }

    // EasyAdmin resolves the dashboard these URLs are mounted under through a cache map of its own, and generating one throws while that pool is empty: the pencils go, the page stays
    public function testAFailingGeneratorLosesThePencilsRatherThanThePage(): void
    {
        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setController')->willReturnSelf();
        $generator->method('setAction')->willReturnSelf();
        $generator->method('setEntityId')->willReturnSelf();
        $generator->method('set')->willReturnSelf();
        $generator->method('generateUrl')->willThrowException(new \RuntimeException('No admin route cache'));

        $this->assertSame([], new BookEditUrlExtension($generator, new BookCustomizationRegistry([], self::registryTranslator()))->book($this->withId(new Book(), 7)));
    }

    // The two lists are written side by side and nothing else keeps them in step: a section added to the page without a field named here would be the only one of the page hovering no pencil at all
    public function testEverySectionThePageOffersHasAPencil(): void
    {
        $urls = $this->extension()->book($this->withId(self::bookHoldingEverySection(), 7));
        $missing = [];

        foreach ($this->anchorsOf(self::bookHoldingEverySection()) as $anchor) {
            if (!isset($urls[$anchor])) {
                $missing[] = $anchor;
            }
        }

        $this->assertSame([], $missing, sprintf('The book page offers %s, which BookEditUrlExtension names no field for.', implode(', ', $missing)));
    }

    private static function bookHoldingEverySection(): Book
    {
        $book = new Book()
            ->setSummary('Un résumé')
            ->setPublished(new \DateTimeImmutable('-1 day'))
            ->setSerie(new Serie())
        ;
        $book->addVideo(new BookVideo());
        $book->addMedia(new BookMedia()->setName('couverture.webp'));
        $book->addExtract(new BookMedia()->setName('page-1.webp'));
        $book->addEdition(new BookEdition()->setKind('digital')->setIsbn('9791092030143'));
        $book->addLink(new BookLink()->setKind('epub_kobo')->setUrl('https://example.com/kobo'));
        $book->addLink(new BookLink()->setKind('podcast_spotify')->setUrl('https://example.com/spotify'));
        $book->addPresse(new BookPresse());
        $book->addMarketing(new BookMarketing());

        return $book;
    }

    /** @return array<int, string> */
    private function anchorsOf(Book $book): array
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $registry = new BookCustomizationRegistry([], self::registryTranslator());

        return array_column(new BookSectionsExtension($registry, $translator)->book($book), 'anchor');
    }

    // A section the site adds is written in a field this bundle cannot know: it names it itself, and then carries the others' pencil
    public function testASectionTheSiteAddsPointsAtTheFieldItNames(): void
    {
        $urls = $this->extension(['idea' => ['template' => 'components/Story/Idea.html.twig', 'field' => 'data']])
            ->book($this->withId(new Book(), 7));

        $this->assertSame('/admin/7/data', $urls['idea']);
    }

    // A section naming no field gets no pencil, as one pointing at nothing of its own deserves
    public function testASectionNamingNoFieldHoldsNoPencil(): void
    {
        $urls = $this->extension(['idea' => ['template' => 'components/Story/Idea.html.twig']])
            ->book($this->withId(new Book(), 7));

        $this->assertArrayNotHasKey('idea', $urls);
    }

    /** @param array<string, array<string, mixed>> $sections */
    private function extension(array $sections = []): BookEditUrlExtension
    {
        $entityId = null;
        $focusField = null;

        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setController')->willReturnSelf();
        $generator->method('setAction')->willReturnSelf();
        $generator->method('setEntityId')->willReturnCallback(function ($id) use (&$entityId, $generator) {
            $entityId = $id;

            return $generator;
        });
        $generator->method('set')->willReturnCallback(function (string $name, $value) use (&$focusField, $generator) {
            $focusField = 'focusField' === $name ? $value : $focusField;

            return $generator;
        });
        // The two names the pencil is made of, so what each section points at is what is asserted
        $generator->method('generateUrl')->willReturnCallback(static function () use (&$entityId, &$focusField): string {
            // No field asked for is the whole screen, which the real generator spells without a "focusField" query at all
            return null === $focusField ? sprintf('/admin/%s', $entityId) : sprintf('/admin/%s/%s', $entityId, $focusField);
        });

        $provider = $this->createStub(BookCustomizationProviderInterface::class);
        $provider->method('getSections')->willReturn($sections);

        return new BookEditUrlExtension($generator, new BookCustomizationRegistry([] === $sections ? [] : [$provider], self::registryTranslator()));
    }

    private function withId(object $entity, int $id): object
    {
        new \ReflectionProperty($entity::class, 'id')->setValue($entity, $id);

        return $entity;
    }

    // The translator the registry asks for: it returns the key as is, which the real one does for a brand - a label that is no translation key is not translated
    private static function registryTranslator(): TranslatorInterface
    {
        $translator = self::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }
}
