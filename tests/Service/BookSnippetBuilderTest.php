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
use c975L\BookBundle\Entity\BookEdition;
use c975L\BookBundle\Entity\BookLink;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\BookSnippetBuilder;
use PHPUnit\Framework\TestCase;

// The schema.org graph a book's, a serie's and a strip's page publish - what is emitted matters as much as what is left out, an empty property being worse than an absent one
class BookSnippetBuilderTest extends TestCase
{
    private BookSnippetBuilder $builder;

    protected function setUp(): void
    {
        // The resolver spells the urls of the books this one points to, which no caller passes in - here it answers as a site serving its books under "/livre"
        $publicUrlResolver = $this->createStub(BookPublicUrlResolver::class);
        $publicUrlResolver
            ->method('resolve')
            ->willReturnCallback(static fn (string $route, array $parameters = []) => 'https://example.org/livre/' . $parameters['slug'])
        ;

        $this->builder = new BookSnippetBuilder($publicUrlResolver);
    }

    public function testABookWithoutTitlePublishesNothing(): void
    {
        $this->assertSame([], $this->builder->buildBook(new Book()));
        $this->assertSame('', $this->builder->buildJson($this->builder->buildBook(new Book())));
    }

    public function testAFilledBookCarriesItsOwnFields(): void
    {
        $snippet = $this->builder->buildBook($this->book(), 'https://example.org/cover.webp', 'https://example.org/livre/tome-1');

        $this->assertSame('https://schema.org', $snippet['@context']);
        $this->assertSame('Book', $snippet['@type']);
        $this->assertSame('Tome 1', $snippet['name']);
        $this->assertSame('https://example.org/livre/tome-1', $snippet['url']);
        $this->assertSame('https://example.org/cover.webp', $snippet['image']);
        $this->assertSame('fr', $snippet['inLanguage']);
        $this->assertSame('2026-01-15', $snippet['datePublished']);
        $this->assertSame(48, $snippet['numberOfPages']);
        $this->assertSame('7-10', $snippet['typicalAgeRange']);
        $this->assertSame(['@type' => 'Person', 'name' => 'Tim Loval', 'url' => 'https://example.org/auteur'], $snippet['author']);
    }

    // The summary is written in a rich text editor, and a graph carries the words only
    public function testTheSummaryReachesTheGraphWithoutItsMarkup(): void
    {
        $book = $this->book()->setSummary("<p>Une histoire\n  de <strong>pirates</strong>&nbsp;!</p>");

        $this->assertSame('Une histoire de pirates !', $this->builder->buildBook($book)['description']);
    }

    // Each edition is a work of its own, which is the only way to say which ISBN belongs to which format
    public function testEachEditionBecomesAWorkExampleOfItsOwn(): void
    {
        $snippet = $this->builder->buildBook($this->book());

        $this->assertSame([
            ['@type' => 'Book', 'bookFormat' => 'https://schema.org/Paperback', 'name' => 'Tome 1', 'isbn' => '9791092030143', 'inLanguage' => 'fr', 'datePublished' => '2026-01-15', 'numberOfPages' => 48],
            ['@type' => 'Book', 'bookFormat' => 'https://schema.org/EBook', 'name' => 'Tome 1', 'isbn' => '9791092030150', 'inLanguage' => 'fr', 'datePublished' => '2026-01-15', 'numberOfPages' => 48],
        ], $snippet['workExample']);

        // The book itself stays formatless while it has two editions, each carrying its own
        $this->assertArrayNotHasKey('bookFormat', $snippet);
    }

    public function testASingleEditionAlsoFormatsTheBookItself(): void
    {
        $book = $this->book();
        $book->removeEdition($book->getEdition('digital'));

        $this->assertSame('https://schema.org/Paperback', $this->builder->buildBook($book)['bookFormat']);
    }

    // An ISBN reserved ahead of the edition it names identifies a book nobody can get yet
    public function testAnEditionNotOutYetIsLeftOut(): void
    {
        $book = $this->book();
        $book->getEdition('digital')->setPublished(new \DateTime('+1 year'));

        $this->assertCount(1, $this->builder->buildBook($book)['workExample']);
    }

    // The kind is the site's own word, matched on rather than mapped - anything naming neither paper nor audio is an ebook
    public function testTheKindOfAnEditionDecidesItsSchemaFormat(): void
    {
        $book = new Book()->setTitle('Tome 1')
            ->addEdition(self::edition('illustrated_paper', '9791092030143'))
            ->addEdition(self::edition('audio', '9791092030150'))
            ->addEdition(self::edition('original_digital', '9791092030167'))
        ;

        $this->assertSame(
            ['https://schema.org/Paperback', 'https://schema.org/AudiobookFormat', 'https://schema.org/EBook'],
            array_column($this->builder->buildBook($book)['workExample'], 'bookFormat')
        );
    }

    // An audiobook is not a Book carrying a format but a type of its own, and a reader searching for one looks for that type
    public function testAnAudioEditionIsAnAudiobookAndNotABook(): void
    {
        $book = new Book()->setTitle('Tome 1')->addEdition(self::edition('audio', '9791092030150'));

        $edition = $this->builder->buildBook($book)['workExample'][0];

        $this->assertSame('Audiobook', $edition['@type']);
        $this->assertSame('https://schema.org/AudiobookFormat', $edition['bookFormat']);
        $this->assertSame('https://schema.org/AudiobookFormat', $this->builder->buildBook($book)['bookFormat']);
    }

    // The platforms are given as identities, never as offers: a price and a stock belong to whoever sells the book. Each edition carries its own, read off the edition rather than guessed from the format the links group under
    public function testAnEditionCarriesTheAddressesOfThePlatformsPublishingIt(): void
    {
        $book = $this->book();
        $book->getEditions()[1]->addLink(new BookLink()->setKind('epub_kobo')->setUrl('https://kobo.example/tome-1'));

        $editions = $this->builder->buildBook($book)['workExample'];

        // The store selling the digital edition reaches it alone, the paperback being sold where the book holds no link
        $this->assertArrayNotHasKey('sameAs', $editions[0]);
        $this->assertSame(['https://kobo.example/tome-1'], $editions[1]['sameAs']);
    }

    // A translation is the same work in another language, not another edition of it: it has its own page, and neither the ISBNs nor the pages of the book it translates
    public function testATranslationIsPairedWithTheBookItTranslates(): void
    {
        $book = $this->book()->setSlug('tome-1');
        $book->addTranslation(new Book()->setTitle('Volume 1')->setSlug('volume-1')->setLanguage('en'));

        $snippet = $this->builder->buildBook($book);

        $this->assertSame([
            ['@type' => 'Book', 'name' => 'Volume 1', 'inLanguage' => 'en', 'url' => 'https://example.org/livre/volume-1'],
        ], $snippet['workTranslation']);
        $this->assertArrayNotHasKey('translationOfWork', $snippet);

        // And the pair read from the other end, the translation naming its original
        $this->assertSame(
            ['@type' => 'Book', 'name' => 'Tome 1', 'inLanguage' => 'fr', 'url' => 'https://example.org/livre/tome-1'],
            $this->builder->buildBook($book->getTranslations()->first())['translationOfWork']
        );
    }

    // The rank is read off the publication dates, the entity carrying no volume number of its own
    public function testABookKnowsItsRankAmongTheVolumesOfItsSerie(): void
    {
        $serie = $this->serie();
        // The one added first, and published last (see serie())
        $second = $serie->getBooks()->get(0);

        $snippet = $this->builder->buildBook($second);

        $this->assertSame(['@type' => 'BookSeries', 'name' => 'La Guilde des Seigneurs'], $snippet['isPartOf']);
        $this->assertSame(2, $snippet['position']);
    }

    public function testASerieListsItsPublishedVolumesOldestFirst(): void
    {
        $snippet = $this->builder->buildSerie($this->serie());

        $this->assertSame('BookSeries', $snippet['@type']);
        $this->assertSame([
            ['@type' => 'Book', 'name' => 'Tome 1', 'position' => 1, 'datePublished' => '2026-01-15'],
            ['@type' => 'Book', 'name' => 'Tome 2', 'position' => 2, 'datePublished' => '2026-06-01'],
        ], $snippet['hasPart']);
    }

    // A book still to come is not part of the serie a reader can follow, and would take the rank of one already out
    public function testAVolumeStillToBePublishedIsLeftOut(): void
    {
        $serie = $this->serie();
        $serie->addBook(new Book()->setTitle('Tome 3')->setPublished(new \DateTime('+1 year')));

        $this->assertCount(2, $this->builder->buildSerie($serie)['hasPart']);
    }

    // A strip is a page of a serie a reader follows, indexed apart from the books of that same serie
    public function testAStripPublishesItsOwnGraph(): void
    {
        $snippet = $this->builder->buildStrip($this->strip(), 'https://example.org/planche.webp', 'https://example.org/planche/la-rencontre');

        $this->assertSame('ComicStory', $snippet['@type']);
        $this->assertSame('La rencontre', $snippet['name']);
        $this->assertSame('https://example.org/planche/la-rencontre', $snippet['url']);
        $this->assertSame('https://example.org/planche.webp', $snippet['image']);
        $this->assertSame('2026-02-01', $snippet['datePublished']);
        $this->assertSame(12, $snippet['position']);
        $this->assertSame('https://example.org/source', $snippet['sameAs']);
        // The serie is the very node its own page publishes, a serie holding strips being the one holding the books
        $this->assertSame(['@type' => 'BookSeries', 'name' => 'La Guilde des Seigneurs'], $snippet['isPartOf']);
        // A strip names no author of its own, the serie naming the one who draws them all
        $this->assertSame(['@type' => 'Person', 'name' => 'Tim Loval'], $snippet['author']);
    }

    public function testAStripCarriesTheCharactersItPutsOnStage(): void
    {
        $snippet = $this->builder->buildStrip($this->strip()->setCharacters('Kalaan, Lilouan'));

        $this->assertSame([
            ['@type' => 'Person', 'name' => 'Kalaan'],
            ['@type' => 'Person', 'name' => 'Lilouan'],
        ], $snippet['character']);
    }

    // Its page is a 404 until the day it comes out, and a graph would index what nobody can read
    public function testAStripStillToComePublishesNothing(): void
    {
        $this->assertSame([], $this->builder->buildStrip($this->strip()->setPublished(new \DateTime('+1 year'))));
        $this->assertSame([], $this->builder->buildStrip(new Strip()->setTitle('Sans date')));
    }

    // An empty field would publish a blank property, which says less than no property at all
    public function testUnfilledFieldsNeverReachTheGraph(): void
    {
        $snippet = $this->builder->buildBook(new Book()->setTitle('Sans rien d\'autre'));

        $this->assertSame(['@context', '@type', 'name'], array_keys($snippet));
    }

    // A "</script>" typed into any field would otherwise close the tag the payload sits in
    public function testTheEncodedPayloadCannotCloseItsOwnScriptTag(): void
    {
        $book = $this->book()->setSummary('</script><script>alert(1)</script>');

        $this->assertStringNotContainsString('</script>', $this->builder->buildJson($this->builder->buildBook($book)));
    }

    private function book(): Book
    {
        return new Book()
            ->setTitle('Tome 1')
            ->setSummary('Une histoire de pirates')
            ->setAuthor('Tim Loval')
            ->setAuthorWebsite('https://example.org/auteur')
            ->setLanguage('fr')
            ->setPublished(new \DateTime('2026-01-15'))
            ->setPages(48)
            ->setAge('7-10')
            ->addEdition(self::edition('paper', '9791092030143'))
            ->addEdition(self::edition('digital', '9791092030150'))
        ;
    }

    private function strip(): Strip
    {
        $strip = new Strip()
            ->setTitle('La rencontre')
            ->setSlug('la-rencontre')
            ->setNumber(12)
            ->setSummary('<p>Deux chevaliers se croisent</p>')
            ->setSourceUrl('https://example.org/source')
            ->setPublished(new \DateTime('2026-02-01'))
        ;

        // The serie names the author of the strips it holds, a strip carrying none of its own
        $this->serie()->setAuthor('Tim Loval')->addStrip($strip);

        return $strip;
    }

    // An edition out well before today, an unreleased one carrying no ISBN a page could print
    private static function edition(string $kind, string $isbn): BookEdition
    {
        return new BookEdition()
            ->setKind($kind)
            ->setIsbn($isbn)
            ->setPublished(new \DateTime('2026-01-15'))
        ;
    }

    // A serie holding its two published volumes, added in the reverse order of their publication so the sorting is actually exercised
    private function serie(): Serie
    {
        $serie = new Serie()->setTitle('La Guilde des Seigneurs');
        $serie->addBook(new Book()->setTitle('Tome 2')->setPublished(new \DateTime('2026-06-01')));
        $serie->addBook($this->book());

        return $serie;
    }
}
