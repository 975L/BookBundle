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
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Serie;
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
