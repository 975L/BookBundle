<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Service;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookCategory;
use c975L\BookBundle\Entity\BookContributor;
use c975L\BookBundle\Entity\BookEdition;
use c975L\BookBundle\Entity\BookEditionFile;
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Enum\BookEditionFileKind;
use c975L\BookBundle\Service\AudioDurationReader;
use c975L\BookBundle\Service\BookOnixBuilder;
use PHPUnit\Framework\TestCase;

// The ONIX message the stores read: one product per edition holding an ISBN, each block filled from the catalog
class BookOnixBuilderTest extends TestCase
{
    private BookOnixBuilder $builder;

    protected function setUp(): void
    {
        // Every recording plays five minutes: how it is read is AudioDurationReader's business, tested there
        $reader = $this->createStub(AudioDurationReader::class);
        $reader->method('seconds')->willReturn(300);

        $this->builder = new BookOnixBuilder($reader, '/public');
    }

    public function testEachEditionWithAnIsbnIsAProduct(): void
    {
        $xpath = $this->read([$this->book()]);

        $this->assertSame('Éditions Test', $xpath->evaluate('string(//o:Header/o:Sender/o:SenderName)'));
        $this->assertSame(['9782488750011', '9782488750028'], $this->values($xpath, '//o:Product/o:ProductIdentifier/o:IDValue'));
        $this->assertSame('example.org.9782488750011', $xpath->evaluate('string(//o:Product[1]/o:RecordReference)'));
    }

    public function testAProductSaysWhatTheEditionIs(): void
    {
        $xpath = $this->read([$this->book()]);
        $digital = '//o:Product[1]/o:DescriptiveDetail';

        $this->assertSame('ED', $xpath->evaluate("string($digital/o:ProductForm)"));
        $this->assertSame('E101', $xpath->evaluate("string($digital/o:ProductFormDetail)"));
        $this->assertSame('Contes', $xpath->evaluate("string($digital/o:Collection/o:TitleDetail/o:TitleElement/o:TitleText)"));
        $this->assertSame('3', $xpath->evaluate("string($digital/o:Collection/o:TitleDetail/o:TitleElement/o:PartNumber)"));
        $this->assertSame('Le Loup', $xpath->evaluate("string($digital/o:TitleDetail/o:TitleElement/o:TitleText)"));
        $this->assertSame('fre', $xpath->evaluate("string($digital/o:Language/o:LanguageCode)"));
        $this->assertSame('24', $xpath->evaluate("string($digital/o:Extent/o:ExtentValue)"));
        $this->assertSame(['3', '8'], $this->values($xpath, "$digital/o:AudienceRange/o:AudienceRangeValue"));
    }

    // The voice is credited on the recording only, which counts its playing time rather than pages
    public function testTheAudioEditionCarriesItsVoiceAndItsDuration(): void
    {
        $xpath = $this->read([$this->book()]);

        $this->assertSame(['A01'], $this->values($xpath, '//o:Product[1]//o:ContributorRole'));
        $this->assertSame(['A01', 'E07'], $this->values($xpath, '//o:Product[2]//o:ContributorRole'));
        $this->assertSame('AJ', $xpath->evaluate('string(//o:Product[2]//o:ProductForm)'));
        $this->assertSame('09', $xpath->evaluate('string(//o:Product[2]//o:Extent/o:ExtentType)'));
        $this->assertSame('300', $xpath->evaluate('string(//o:Product[2]//o:Extent/o:ExtentValue)'));
    }

    // The recording's detail tells the format of the file Google fetches: MP3 unless an M4A was dropped, which is AAC
    public function testTheAudioDetailFollowsTheRecordingsFormat(): void
    {
        $this->assertSame('A103', $this->read([$this->book()])->evaluate('string(//o:Product[2]//o:ProductFormDetail)'));

        $book = $this->book();
        $book->getEdition('audio')?->setFileOf(BookEditionFileKind::Audio, new BookEditionFile()->setName('medias/book/editions/loup.M4A'));

        $this->assertSame('A107', $this->read([$book])->evaluate('string(//o:Product[2]//o:ProductFormDetail)'));
    }

    // Each scheme's first subject is the main one, a Thema qualifier going under its own scheme
    public function testTheCategoriesAreTheSubjects(): void
    {
        $xpath = $this->read([$this->book()]);
        $subjects = '//o:Product[1]//o:Subject';

        $this->assertSame(['29', '93', '98', '10'], $this->values($xpath, "$subjects/o:SubjectSchemeIdentifier"));
        $this->assertSame(['3730', 'YBCS1', '5AC', 'JUV010000'], $this->values($xpath, "$subjects/o:SubjectCode"));
        $this->assertSame(3.0, $xpath->evaluate('count(' . $subjects . '[o:MainSubject])'));
    }

    public function testAPricedEditionIsSoldAtItsPrice(): void
    {
        $xpath = $this->read([$this->book()]);

        $this->assertSame('4.99', $xpath->evaluate('string(//o:Product[1]//o:Price/o:PriceAmount)'));
        $this->assertSame('EUR', $xpath->evaluate('string(//o:Product[1]//o:Price/o:CurrencyCode)'));
        $this->assertSame('04', $xpath->evaluate('string(//o:Product[2]//o:UnpricedItemType)'));
    }

    // A price of 0 is a free book, which the stores want said as such rather than priced at nothing
    public function testAZeroPriceIsFree(): void
    {
        $book = $this->book();
        $book->getEditions()->first()->setPrice(0);
        $xpath = $this->read([$book]);

        $this->assertSame('01', $xpath->evaluate('string(//o:Product[1]//o:UnpricedItemType)'));
        $this->assertSame(0.0, $xpath->evaluate('count(//o:Product[1]//o:Price)'));
    }

    // A book dated ahead is announced, for the stores to take preorders
    public function testABookStillToComeIsForthcoming(): void
    {
        $book = $this->book()->setPublished(new \DateTime('+1 month'));
        $xpath = $this->read([$book]);

        $this->assertSame('02', $xpath->evaluate('string(//o:Product[1]/o:PublishingDetail/o:PublishingStatus)'));
        $this->assertSame('10', $xpath->evaluate('string(//o:Product[1]//o:ProductAvailability)'));
        $this->assertSame('02', $xpath->evaluate('string(//o:Product[2]//o:UnpricedItemType)'));
        $this->assertSame(new \DateTime('+1 month')->format('Ymd'), $xpath->evaluate('string(//o:Product[1]//o:PublishingDate/o:Date)'));
    }

    // A store reads the rights before offering the book at all, and whether the file is locked before adding a lock of its own
    public function testAProductCarriesItsRightsAndNoDrm(): void
    {
        $xpath = $this->read([$this->book()]);

        $this->assertSame('01', $xpath->evaluate('string(//o:Product[1]/o:PublishingDetail/o:SalesRights/o:SalesRightsType)'));
        $this->assertSame('WORLD', $xpath->evaluate('string(//o:Product[1]/o:PublishingDetail/o:SalesRights/o:Territory/o:RegionsIncluded)'));
        $this->assertSame('00', $xpath->evaluate('string(//o:Product[1]/o:DescriptiveDetail/o:EpubTechnicalProtection)'));
        $this->assertSame(0.0, $xpath->evaluate('count(//o:Product[2]/o:DescriptiveDetail/o:EpubTechnicalProtection)'));
    }

    // A feed sends only the editions it keeps - those ticked for its channel
    public function testTheEditionsAFeedKeepsAreTheOnlyProducts(): void
    {
        $document = new \DOMDocument();
        $document->loadXML($this->builder->build([$this->book()], 'Éditions Test', 'https://example.org/', BookOnixBuilder::isEbook(...)));
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('o', 'http://ns.editeur.org/onix/3.0/reference');

        $this->assertSame(['9782488750011'], $this->values($xpath, '//o:Product/o:ProductIdentifier/o:IDValue'));
    }

    // A kind the site names on its own and that names no file is a printed book, never an ebook a store would sell as a file
    public function testAnUnknownKindIsAPrintedBook(): void
    {
        $edition = new BookEdition()->setKind('hardcover')->setIsbn('9782488750011');
        $book = new Book()->setTitle('Relié')->setPublished(new \DateTime('2026-01-01'))->addEdition($edition);

        $this->assertFalse(BookOnixBuilder::isEbook($edition));
        $this->assertSame('BC', $this->read([$book])->evaluate('string(//o:Product/o:DescriptiveDetail/o:ProductForm)'));
    }

    public function testAnEditionWithoutIsbnIsNoProduct(): void
    {
        $book = new Book()->setTitle('Sans ISBN')->setPublished(new \DateTime('2026-01-01'));
        $book->addEdition(new BookEdition()->setKind('paper'));

        $this->assertSame(0.0, $this->read([$book])->evaluate('count(//o:Product)'));
    }

    // An ISBN-10 would go out truncated under an ISBN-13 identifier
    public function testAnEditionWithAnIsbn10IsNoProduct(): void
    {
        $book = new Book()->setTitle('Ancien ISBN')->setPublished(new \DateTime('2026-01-01'));
        $book->addEdition(new BookEdition()->setKind('paper')->setIsbn('2-07-036024-X'));

        $this->assertSame(0.0, $this->read([$book])->evaluate('count(//o:Product)'));
    }

    private function book(): Book
    {
        $author = new Contributor()->setName('Jeanne Auteur');
        $book = new Book()
            ->setTitle('Le Loup')
            ->setSummary('<p>Un loup &amp; un agneau.</p>')
            ->setLanguage('fr')
            ->setAge('3-8')
            ->setAuthor($author)
            ->setSerie(new Serie()->setTitle('Contes'))
            ->setNumber(3)
            ->setPublished(new \DateTime('2026-01-01'));

        $book->addEdition(new BookEdition()->setKind('digital')->setIsbn('978-2-488750-01-1')->setPages(24)->setPrice(499));
        $book->addEdition(new BookEdition()->setKind('audio')->setIsbn('9782488750028'));
        $book->addContributor(new BookContributor()->setContributor(new Contributor()->setName('La Voix'))->setRole('narrator'));
        $book->addMedia(new BookMedia()->setKind('audio_mp3')->setName('medias/loup.mp3'));
        $book->addCategory(new BookCategory()->setTitle('Albums')->setCodes(['clil' => '3730', 'thema' => 'YBCS1 5AC', 'bisac' => 'JUV010000']));

        return $book;
    }

    /** @param Book[] $books */
    private function read(array $books): \DOMXPath
    {
        $document = new \DOMDocument();
        $document->loadXML($this->builder->build($books, 'Éditions Test', 'https://example.org/'));
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('o', 'http://ns.editeur.org/onix/3.0/reference');

        return $xpath;
    }

    /** @return list<string> */
    private function values(\DOMXPath $xpath, string $query): array
    {
        $values = [];
        foreach ($xpath->query($query) ?: [] as $node) {
            $values[] = $node->textContent;
        }

        return $values;
    }
}
