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
use c975L\BookBundle\Entity\BookLink;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use c975L\BookBundle\Twig\BookSectionsExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

// The list is read twice by a display page - once to build the summary of anchors, once to decide what to render - so what it holds is what the page shows, and nothing else
class BookSectionsExtensionTest extends TestCase
{
    // A book with nothing filled in still says when it comes out and who wrote it, so that one section is never absent
    public function testABookWithNothingFilledInStillHoldsItsInformations(): void
    {
        $this->assertSame(['informations'], $this->anchors(new Book()));
    }

    // The order is the page's own, not the order the conditions happen to be true in
    public function testTheSectionsComeInThePagesOwnOrder(): void
    {
        $book = new Book()->setSummary('Un résumé')->setSerie(new Serie());

        $this->assertSame(['resume', 'serie', 'informations'], $this->anchors($book));
    }

    // A store card is only printed for a book actually out: the buttons of an unpublished one lead to pages that do not exist yet
    public function testTheStoresAreOnlyOfferedOnceTheBookIsOut(): void
    {
        $link = new BookLink()->setKind('epub_kobo')->setUrl('https://example.com/kobo');

        $out = new Book()->setPublished(new \DateTimeImmutable('-1 day'));
        $out->addLink($link);
        $this->assertContains('shops', $this->anchors($out));

        $coming = new Book()->setPublished(new \DateTimeImmutable('+1 month'));
        $coming->addLink($link);
        $this->assertNotContains('shops', $this->anchors($coming));
    }

    // A published book with no store link at all has no card to print either, and so no anchor pointing at an empty one
    public function testAPublishedBookWithNoStoreLinkOffersNoStoreSection(): void
    {
        $this->assertNotContains('shops', $this->anchors(new Book()->setPublished(new \DateTimeImmutable('-1 day'))));
    }

    // The two groups share one card, so either of them on its own opens it
    public function testAPodcastLinkAloneOpensTheListeningSection(): void
    {
        $book = new Book();
        $book->addLink(new BookLink()->setKind('podcast_spotify')->setUrl('https://example.com/spotify'));

        $this->assertContains('podcasts', $this->anchors($book));
    }

    // A book is read in its own language, whatever the language the visitor reached it in
    public function testTheLabelsAreTranslatedInTheBooksOwnLanguage(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $key, array $parameters, ?string $domain, ?string $locale): string => $key . '@' . $domain . '/' . $locale
        );

        $sections = new BookSectionsExtension(new BookCustomizationRegistry([]), $translator)->book(new Book()->setLanguage('en'));

        $this->assertSame('label.informations@book/en', $sections[0]['label']);
    }

    // A serie with no book yet is a page holding its summary alone - an anchor to an empty shelf points at nothing
    public function testASerieOffersItsBooksOnlyOnceItHasSome(): void
    {
        $serie = new Serie();
        $serie->setSummary('Une série');

        $this->assertSame(['resume'], array_column(new BookSectionsExtension(new BookCustomizationRegistry([]), $this->translator())->serie($serie), 'anchor'));
    }

    /**
     * @return array<int, string>
     */
    private function anchors(Book $book): array
    {
        return array_column(new BookSectionsExtension(new BookCustomizationRegistry([]), $this->translator())->book($book), 'anchor');
    }

    private function translator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }
}
