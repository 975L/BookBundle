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
use c975L\BookBundle\Entity\BookPrintable;
use c975L\BookBundle\Entity\Character;
use c975L\BookBundle\Entity\CharacterMedia;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use c975L\BookBundle\Twig\BookSectionsExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

// The list is read twice by a display page - once to build the summary of anchors, once to decide what to render - so what it holds is what the page shows, and nothing else
class BookSectionsExtensionTest extends TestCase
{
    // The listening pages list a book by what they can play, not by what is filed as audio
    public function testOnlyTheBooksHoldingARecordingAreKept(): void
    {
        $recorded = new Book()->addMedia(new BookMedia()->setKind('audio_mp3')->setName('histoire.mp3'));
        $silent = new Book()->addMedia(new BookMedia()->setKind('audio_mp3')->setName('histoire.pdf'));

        $this->assertSame([$recorded], BookSectionsExtension::withAudio([$silent, $recorded, new Book()]));
    }

    // A book with nothing filled in still says when it comes out and who wrote it, so that one section is never absent
    public function testABookWithNothingFilledInStillHoldsItsInformations(): void
    {
        $this->assertSame(['informations'], $this->anchors(new Book()));
    }

    // The summary is no section: it is the sentence the book opens on, laid under the hero by the page itself, with neither title nor anchor
    public function testTheSummaryIsNotASectionOfThePage(): void
    {
        $this->assertSame(['informations'], $this->anchors(new Book()->setSummary('Un résumé')));
    }

    // What a reader prints comes last among what the book offers, the informations closing the page
    public function testThePrintablesComeRightBeforeTheInformations(): void
    {
        $book = new Book()->setPublished(new \DateTimeImmutable('-1 day'));
        $book->addMarketing(new BookMarketing());
        $book->addPrintable(new BookPrintable());

        $this->assertSame(['marketing', 'printables', 'informations'], $this->anchors($book));
    }

    // A book still to come keeps its documents to itself until it is out, like its recording
    public function testABookNotOutYetOffersNothingToPrint(): void
    {
        $coming = new Book()->setPublished(new \DateTimeImmutable('+1 month'));
        $coming->addPrintable(new BookPrintable());

        $this->assertNotContains('printables', $this->anchors($coming));
        $this->assertNotContains('printables', $this->anchors(new Book()->addPrintable(new BookPrintable())));
    }

    // A book whose every document waits for its day shows no section yet, and the grouping leaves out what is not out
    public function testADocumentDatedAheadStaysOffThePage(): void
    {
        $later = new BookPrintable()->setKind('small')->setPublishedOn(new \DateTimeImmutable('+7 days'));
        $this->assertNotContains('printables', $this->anchors(new Book()->setPublished(new \DateTimeImmutable('-1 month'))->addPrintable($later)));

        $out = new BookPrintable()->setKind('small');
        $book = new Book()->addPrintable($later)->addPrintable($out);
        $this->assertSame([['label' => 'Small', 'printables' => [$out]]], $this->extensionNamingLevels(['small' => 'Small'])->printablesByKind($book));
    }

    // A level captions documents only if it holds one, in the order the site names them whatever the order of the rows
    public function testThePrintablesAreGroupedInTheOrderTheSiteNamesItsLevels(): void
    {
        $big = new BookPrintable()->setKind('big');
        $small = new BookPrintable()->setKind('small');
        $book = new Book()->addPrintable($big)->addPrintable($small);

        $groups = $this->extensionNamingLevels(['small' => 'Small', 'empty' => 'Empty', 'big' => 'Big'])->printablesByKind($book);

        $this->assertSame([['label' => 'Small', 'printables' => [$small]], ['label' => 'Big', 'printables' => [$big]]], $groups);
    }

    // A level taken out of the vocabulary must not hide what was uploaded under it, and a site naming none gets one group under no label
    public function testADocumentUnderNoLevelTheSiteStillNamesStaysPrinted(): void
    {
        $orphan = new BookPrintable()->setKind('gone');
        $book = new Book()->addPrintable($orphan);

        $this->assertSame([['label' => '', 'printables' => [$orphan]]], $this->extensionNamingLevels(['small' => 'Small'])->printablesByKind($book));
        $this->assertSame([['label' => '', 'printables' => [$orphan]]], $this->extension($this->translator())->printablesByKind($book));
    }

    // The block links from another page, so it has to aim at the anchor the site renamed the section to
    public function testTheAnchorOfASectionIsTheOneTheSiteGaveIt(): void
    {
        $this->assertSame('printables', $this->extension($this->translator())->sectionAnchor('printables'));
        $this->assertSame('colorier', $this->extension($this->translator(), ['printables' => ['anchor' => 'colorier']])->sectionAnchor('printables'));
    }

    // The order is the page's own, not the order the conditions happen to be true in
    public function testTheSectionsComeInThePagesOwnOrder(): void
    {
        $book = new Book();
        $book->addMarketing(new BookMarketing());
        $book->addPresse(new BookPresse());

        $this->assertSame(['presse', 'marketing', 'informations'], $this->anchors($book));
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

    // A free book is obtained rather than bought: the same section and anchor, under a name and a sentence of its own (see Book::isFree())
    public function testAFreeBookIsObtainedRatherThanBought(): void
    {
        $book = new Book()->setPublished(new \DateTimeImmutable('-1 day'));
        $book->addLink(new BookLink()->setKind('epub_kobo')->setUrl('https://example.com/kobo'));
        $this->assertSame('[label.buy]', $this->shops($book)['label']);

        $book->addEdition(new BookEdition()->setKind('digital')->setPrice(0));
        $shops = $this->shops($book);

        $this->assertSame('shops', $shops['anchor']);
        $this->assertSame('[label.get_free]', $shops['label']);
        $this->assertSame('[label.get_free-intro]', $shops['intro']);
    }

    // The campaign card is opened by the address alone: a book with an end date and nowhere to pledge has nothing to print
    public function testTheCampaignIsOfferedByTheAddressAndNotByTheDate(): void
    {
        $dateOnly = new Book()->setCrowdfundingEndDate(new \DateTime('+1 month'));
        $this->assertNotContains('crowdfunding', $this->anchors($dateOnly));

        $book = new Book()->setCrowdfunding('https://example.com/campagne');
        $this->assertContains('crowdfunding', $this->anchors($book));
    }

    // An ended campaign keeps its card - the component prints the date and drops the button, where dropping the section would take the page's own summary entry with it
    public function testAnEndedCampaignKeepsItsSection(): void
    {
        $book = new Book()
            ->setCrowdfunding('https://example.com/campagne')
            ->setCrowdfundingEndDate(new \DateTime('-1 month'))
        ;

        $this->assertContains('crowdfunding', $this->anchors($book));
    }

    // A book still being funded is not yet a book being sold: the campaign comes before the stores
    public function testTheCampaignComesBeforeTheStores(): void
    {
        $book = new Book()->setCrowdfunding('https://example.com/campagne')->setPublished(new \DateTimeImmutable('-1 day'));
        $book->addLink(new BookLink()->setKind('epub_kobo')->setUrl('https://example.com/kobo'));

        $anchors = $this->anchors($book);

        $this->assertLessThan(array_search('shops', $anchors, true), array_search('crowdfunding', $anchors, true));
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

    // A book still to come keeps its recording to itself: the card and the hero's button to it would point at nothing
    public function testARecordingAloneOpensNoSectionOnABookToCome(): void
    {
        $book = new Book()->setPublished(new \DateTimeImmutable('+1 year'));
        $book->addAudio(new BookMedia()->setName('histoire.mp3'));

        $this->assertNotContains('podcasts', $this->anchors($book));
    }

    // Where it will be heard is said ahead of it
    public function testAPodcastLinkStillOpensTheSectionOnABookToCome(): void
    {
        $book = new Book()->setPublished(new \DateTimeImmutable('+1 year'));
        $book->addAudio(new BookMedia()->setName('histoire.mp3'));
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

        $sections = $this->extension($translator)->book(new Book()->setLanguage('en'));

        $this->assertSame('label.informations@book/en', $sections[0]['label']);
    }

    // A serie with nothing to list yet offers no section at all: its summary is not one - it is the sentence the page opens on, laid under the hero as a book's is - and an anchor to an empty shelf points at nothing
    public function testASerieOffersItsBooksOnlyOnceItHasSome(): void
    {
        $serie = new Serie();
        $serie->setSummary('Une série');

        $this->assertSame([], array_column($this->extension($this->translator())->serie($serie), 'anchor'));
    }

    // Naming who speaks is not presenting them: a serie whose characters carry neither face nor text says their names in the row of chips under the planches, and opens no section to repeat them
    public function testCharactersWithNothingToShowOpenNoSection(): void
    {
        $serie = new Serie();
        $serie->addCharacter(new Character()->setName('Loris'));

        $this->assertNotContains('characters', array_column($this->extension($this->translator())->serie($serie), 'anchor'));
    }

    // A presentation is enough on its own, a face being what a serie uploads later
    public function testAPresentedCharacterOpensTheSection(): void
    {
        $serie = new Serie();
        $serie->addCharacter(new Character()->setName('Loris')->setPresentation('Le grand frère'));

        $this->assertContains('characters', array_column($this->extension($this->translator())->serie($serie), 'anchor'));
    }

    // And so is a face, the presentation being a text a serie writes later
    public function testACharacterCarryingAFaceOpensTheSection(): void
    {
        $character = new Character()->setName('Loris');
        $character->addMedia(new CharacterMedia()->setName('loris.jpg'));

        $serie = new Serie();
        $serie->addCharacter($character);

        $this->assertContains('characters', array_column($this->extension($this->translator())->serie($serie), 'anchor'));
    }

    // The book's recording is listened to and downloaded in the "Listen" card, which already holds the podcast apps. It belongs to the book, its edition saying only the ISBN it comes out under (see BookEditionType)
    public function testTheRecordingIsListenedToInTheListeningCard(): void
    {
        $book = new Book()->setPublished(new \DateTimeImmutable('-1 day'));
        $book->addAudio(new BookMedia()->setName('histoire.mp3'));

        $this->assertSame(['podcasts', 'informations'], $this->anchors($book));
    }

    // A file left over from when a book carried a trailer or a filmed episode opens no card any more: the two kinds are gone, and a card is only offered for what a page actually prints
    public function testALeftoverTrailerOrFilmedEpisodeOpensNoCard(): void
    {
        $book = new Book();
        $book->addMedia(new BookMedia()->setName('bande-annonce.mp4')->setKind('trailer'));
        $book->addMedia(new BookMedia()->setName('episode.mp4')->setKind('podcast'));

        $this->assertSame(['informations'], $this->anchors($book));
    }

    // The first image, not the first file: a catalog storing a recording before its cover would print an mp3 in an <img>
    public function testTheCoverIsTheFirstImageTheBookHolds(): void
    {
        $book = new Book();
        $book->addAudio(new BookMedia()->setName('histoire.mp3'));
        $book->addMedia(new BookMedia()->setName('couverture.webp'));

        $this->assertSame('couverture.webp', BookSectionsExtension::cover($book)?->getName());
        $this->assertNull(BookSectionsExtension::cover(new Book()));
    }

    // The background and the back cover are images of the book, but neither stands in for a cover: the page printed its own background in the cover's frame
    public function testTheImagesStandingForSomethingElseNeverStandInForTheCover(): void
    {
        $book = new Book();
        $book->addBackground(new BookMedia()->setName('fond.webp'));
        $book->addBackCover(new BookMedia()->setName('quatrieme.webp'));

        $this->assertNull(BookSectionsExtension::cover($book));

        $book->addPage(new BookMedia()->setName('page-1.webp'));

        $this->assertSame('page-1.webp', BookSectionsExtension::cover($book)?->getName());
    }

    // The pages are a collection of their own (Book::getPages()), uploaded on their own field: a recording does not enter it
    public function testThePagesAreTheirOwnCollection(): void
    {
        $book = new Book();
        $book->addAudio(new BookMedia()->setName('histoire.mp3'));

        $this->assertNotContains('pages', $this->anchors($book));

        $book->addPage(new BookMedia()->setName('page-1.webp'));

        $this->assertContains('pages', $this->anchors($book));
    }

    // The order of the page is the site's, a section it says nothing about coming after the ones it placed
    public function testTheSiteDecidesTheOrderOfTheSections(): void
    {
        $book = new Book();
        $book->addMarketing(new BookMarketing());
        $book->addPresse(new BookPresse());

        $anchors = array_column($this->extension($this->translator(), [
            'informations' => [],
            'presse' => [],
        ])->book($book), 'anchor');

        $this->assertSame(['informations', 'presse', 'marketing'], $anchors);
    }

    // The word a site gives a section and the id it is reached by are its own, translated in its own catalog and in the book's language
    public function testTheSiteDecidesTheNameAndTheAnchorOfASection(): void
    {
        $sections = $this->extension($this->translator(), [
            'presse' => ['anchor' => 'id.idea', 'label' => 'label.idea', 'domain' => 'messages'],
        ])->book(self::withPresse());

        $this->assertSame(['anchor' => 'id.idea', 'label' => 'label.idea'], [
            'anchor' => $sections[0]['anchor'],
            'label' => $sections[0]['label'],
        ]);
    }

    // The icon, the entrance effect and the fragment the site adds inside the card travel with the section, the page having nothing to look up again
    public function testTheLookAndTheAddedFragmentTravelWithTheSection(): void
    {
        $declaration = ['icon' => ['images/lightbulb.svg', 'white', 24, 24], 'animation' => 'slide-in-right', 'template' => 'story/_idea.html.twig'];

        $sections = $this->extension($this->translator(), ['presse' => $declaration])->book(self::withPresse());

        $this->assertSame($declaration['icon'], $sections[0]['icon']);
        $this->assertSame('slide-in-right', $sections[0]['animation']);
        $this->assertSame('story/_idea.html.twig', $sections[0]['template']);
    }

    // A site declaring nothing gets exactly the page it always had
    public function testASiteDeclaringNothingGetsThePageUntouched(): void
    {
        $sections = $this->extension($this->translator())->book(self::withPresse());

        $this->assertSame(['presse', 'informations'], array_column($sections, 'anchor'));
        $this->assertArrayNotHasKey('animation', $sections[0]);
    }

    // The sentence opening a section comes from the catalog and from nowhere else: a section it says nothing about carries none, rather than printing its own key under its title
    public function testARubricOnlyCarriesTheOpeningLineItsCatalogHolds(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $key): string => 'label.presse-intro' === $key ? 'Ce qu\'on en a dit.' : $key
        );

        $sections = $this->extension($translator)->book(self::withPresse());

        $this->assertSame('Ce qu\'on en a dit.', $sections[0]['intro']);
        // The informations have none: the key returned as is is what the translator answers when nothing is written
        $this->assertArrayNotHasKey('intro', $sections[1]);
    }

    // A book holding one section other than its own civil status: what a site's declaration is laid over
    private static function withPresse(): Book
    {
        $book = new Book();
        $book->addPresse(new BookPresse());

        return $book;
    }

    /**
     * @return array<int, string>
     */
    // A section this bundle does not have: the site declares it with the fragment printing it, and it takes its place in the page like the others
    public function testASectionTheSiteAddsTakesItsPlaceInThePage(): void
    {
        $sections = $this->extension($this->translator(), [
            'idea' => ['anchor' => 'idee', 'label' => "L'idée", 'template' => 'components/Story/Idea.html.twig'],
            'informations' => [],
        ])->book(new Book());

        $this->assertSame(['idee', 'informations'], array_column($sections, 'anchor'));
        $this->assertSame('components/Story/Idea.html.twig', $sections[0]['template']);
        $this->assertSame("L'idée", $sections[0]['label']);
    }

    // Without a fragment the card would come out titled and empty: the declaration then only dresses a section of the bundle, which it always did
    public function testADeclarationWithoutAFragmentOpensNoSection(): void
    {
        $sections = $this->extension($this->translator(), ['idea' => ['label' => "L'idée"]])->book(new Book());

        $this->assertSame(['informations'], array_column($sections, 'anchor'));
    }

    // The hero is no section, and it is yet where a site wants a line of its own: the declaration reads the same way, by the key
    public function testAFragmentIsDeclaredForAPartOfThePageThatIsNoSection(): void
    {
        $extension = $this->extension($this->translator(), ['hero' => ['template' => 'components/Story/Idea.html.twig']]);

        $this->assertSame('components/Story/Idea.html.twig', $extension->sectionTemplate('hero'));
        $this->assertSame('', $extension->sectionTemplate('informations'));
    }

    // Declaring a fragment for the hero opens no section: it is printed by the hero itself, and one more card at the head of the page would be the same thing written twice
    public function testAFragmentDeclaredForTheHeroOpensNoSection(): void
    {
        $sections = $this->extension($this->translator(), ['hero' => ['template' => 'components/Story/Idea.html.twig']])->book(new Book());

        $this->assertSame(['informations'], array_column($sections, 'anchor'));
    }

    // The stores' section of a book, read through a translator that marks what it translated, so a sentence it holds tells itself from one it lacks
    /** @return array<string, mixed> */
    private function shops(Book $book): array
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => '[' . $key . ']');

        return array_column($this->extension($translator)->book($book), null, 'key')['shops'];
    }

    private function anchors(Book $book): array
    {
        return array_column($this->extension($this->translator())->book($book), 'anchor');
    }

    /** @param array<string, array<string, mixed>> $sections */
    private function extension(TranslatorInterface $translator, array $sections = []): BookSectionsExtension
    {
        $registry = new BookCustomizationRegistry([] === $sections ? [] : [$this->siteDeclaring($sections)], self::registryTranslator());

        return new BookSectionsExtension($registry, $translator);
    }

    /** @param array<string, string> $kinds */
    private function extensionNamingLevels(array $kinds): BookSectionsExtension
    {
        $provider = $this->createStub(BookCustomizationProviderInterface::class);
        $provider->method('getPrintableKinds')->willReturn($kinds);

        return new BookSectionsExtension(new BookCustomizationRegistry([$provider], self::registryTranslator()), $this->translator());
    }

    /** @param array<string, array<string, mixed>> $sections */
    private function siteDeclaring(array $sections): BookCustomizationProviderInterface
    {
        $provider = $this->createStub(BookCustomizationProviderInterface::class);
        $provider->method('getSections')->willReturn($sections);

        return $provider;
    }

    private function translator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }

    // The translator the registry asks for: it returns the key as is, which the real one does for a brand - a label that is no translation key is not translated
    private static function registryTranslator(): TranslatorInterface
    {
        $translator = self::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }
}
