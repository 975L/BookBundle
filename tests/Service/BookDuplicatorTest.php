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
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Entity\BookVideo;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\SerieMedia;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Entity\StripMedia;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Repository\SerieRepository;
use c975L\BookBundle\Repository\StripRepository;
use c975L\BookBundle\Service\BookDuplicator;
use c975L\UiBundle\Entity\Block;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

// What a duplicated serie, book or strip carries - and what it must not carry twice, a book's files and platform links being reached both from the book and from the version they were published in
class BookDuplicatorTest extends TestCase
{
    private BookDuplicator $duplicator;

    protected function setUp(): void
    {
        $bookRepository = $this->createStub(BookRepository::class);
        $bookRepository->method('findOneBy')->willReturn(null);
        $serieRepository = $this->createStub(SerieRepository::class);
        $serieRepository->method('findOneBy')->willReturn(null);
        $stripRepository = $this->createStub(StripRepository::class);
        $stripRepository->method('findOneBy')->willReturn(null);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('copie');

        // No project directory holding the uploaded files, so no file is copied here - what the copy carries of a media is checked on its columns
        $this->duplicator = new BookDuplicator(
            $bookRepository,
            $security,
            $serieRepository,
            new AsciiSlugger(),
            $stripRepository,
            $translator,
            sys_get_temp_dir(),
        );
    }

    public function testTheCopyOfASerieCarriesItsMediasAndBlocksButNotItsBooks(): void
    {
        $serie = new Serie()
            ->setTitle('La Guilde des Seigneurs')
            ->setSlug('la-guilde-des-seigneurs')
            ->setKind('strip')
            ->setLanguage('fr')
            ->setAuthor('Tim Loval');
        $serie->addCover(new SerieMedia()->setName('medias/book/series/cover-guilde.webp'));
        $serie->addLogo(new SerieMedia()->setName('medias/book/series/logo-guilde.webp'));
        $serie->addBlock(new Block()->setKind('article')->setData(['text' => 'Un texte']));
        $serie->addBook(new Book()->setTitle('Tome 1'));

        $copy = $this->duplicator->duplicateSerie($serie);

        $this->assertSame('La Guilde des Seigneurs (copie)', $copy->getTitle());
        $this->assertSame('la-guilde-des-seigneurs-copie', $copy->getSlug());
        $this->assertSame('strip', $copy->getKind());
        $this->assertSame('Tim Loval', $copy->getAuthor());
        $this->assertCount(2, $copy->getMedias());
        $this->assertCount(1, $copy->getCovers());
        $this->assertCount(1, $copy->getLogos());
        $this->assertCount(1, $copy->getBlocks());
        $this->assertCount(0, $copy->getBooks());
        $this->assertSame(['text' => 'Un texte'], $copy->getBlocks()->first()->getData());
    }

    public function testTheCopyOfABookCarriesEachFileAndLinkOnce(): void
    {
        $book = $this->book();

        $copy = $this->duplicator->duplicateBook($book);

        $this->assertCount(2, $copy->getEditions());
        // The files and the platforms belong to the book: three rows in, three out, none copied a second time by the edition it came out under
        $this->assertCount(3, $copy->getMedias());
        $this->assertCount(2, $copy->getLinks());
        $this->assertNotSame($book->getEditions()->first(), $copy->getEditions()->first());
        // An edition says only what the book comes out under, and that is exactly what the copy takes over
        $this->assertSame('9791234567890', $copy->getEditions()->first()->getIsbn());
        $this->assertSame(48, $copy->getEditions()->first()->getPages());
        $this->assertSame('BD', $copy->getEditions()->first()->getFormat());
    }

    public function testTheCopyOfABookStaysInItsSerieAndKeepsItsOwnFields(): void
    {
        $serie = new Serie()->setTitle('La Guilde des Seigneurs')->setSlug('la-guilde-des-seigneurs');
        $book = $this->book()->setSerie($serie);

        $copy = $this->duplicator->duplicateBook($book);

        $this->assertSame('Tome 1 (copie)', $copy->getTitle());
        $this->assertSame('tome-1-copie', $copy->getSlug());
        $this->assertSame($serie, $copy->getSerie());
        $this->assertSame(1, $copy->getNumber());
        $this->assertSame(['showcase' => true], $copy->getData());
        $this->assertNull($copy->getId());
    }

    public function testAHostedVideoIsCopiedWithoutTheNameBuiltOffItsUrl(): void
    {
        $book = new Book()->setTitle('Tome 1')->setSlug('tome-1');
        $book->addVideo(new BookVideo()->setTitle('La bande-annonce')->setYoutubeUrl('aBcDeFgHiJk'));

        $copy = $this->duplicator->duplicateBook($book);

        $video = $copy->getVideos()->first();
        $this->assertSame('aBcDeFgHiJk', $video->getYoutubeUrl());
        $this->assertSame('La bande-annonce', $video->getTitle());
        // The column is unique, and the name a hosted video gets is built off that very url - the copy holds none rather than the one the source already took
        $this->assertNull($video->getName());
    }

    public function testTheCopyOfAStripCarriesItsPlatesAndBlocks(): void
    {
        $strip = new Strip()
            ->setTitle('Le tournoi')
            ->setSlug('le-tournoi')
            ->setNumber(3)
            ->setCharacters('Hardy, Kaandreï');
        $strip->addMedia(new StripMedia()->setName('medias/book/strips/planche-1.webp'));
        $strip->addBlock(new Block()->setKind('article'));

        $copy = $this->duplicator->duplicateStrip($strip);

        $this->assertSame('Le tournoi (copie)', $copy->getTitle());
        $this->assertSame('le-tournoi-copie', $copy->getSlug());
        $this->assertSame(3, $copy->getNumber());
        $this->assertSame('Hardy, Kaandreï', $copy->getCharacters());
        $this->assertCount(1, $copy->getMedias());
        $this->assertCount(1, $copy->getBlocks());
        $this->assertNull($copy->getMedias()->first()->getName());
    }

    public function testALongTitleIsCutToWhatTheColumnTakes(): void
    {
        $serie = new Serie()
            ->setTitle(str_repeat('a', 50))
            ->setSlug(str_repeat('a', 50));

        $copy = $this->duplicator->duplicateSerie($serie);

        $this->assertLessThanOrEqual(50, mb_strlen((string) $copy->getTitle()));
        $this->assertLessThanOrEqual(50, mb_strlen((string) $copy->getSlug()));
        $this->assertStringEndsWith('(copie)', (string) $copy->getTitle());
    }

    // A book of two versions, its cover belonging to the book itself and its pages to the printed one - the shape the routing of files and links is checked against
    private function book(): Book
    {
        $book = new Book()
            ->setTitle('Tome 1')
            ->setSlug('tome-1')
            ->setAuthor('Tim Loval')
            ->setNumber(1)
            ->setData(['showcase' => true]);

        $printed = new BookEdition()->setKind('printed')->setIsbn('9791234567890')->setPages(48)->setFormat('BD');
        $audio = new BookEdition()->setKind('audio');
        $book->addEdition($printed);
        $book->addEdition($audio);

        $book->addCover(new BookMedia()->setName('medias/book/books/cover-tome-1.webp'));
        $book->addMedia(new BookMedia()->setKind('extract')->setName('medias/book/books/page-1.webp'));
        $book->addMedia(new BookMedia()->setKind('extract')->setName('medias/book/books/page-2.webp'));
        $book->addLink(new BookLink()->setKind('epub_kobo')->setUrl('https://example.org/kobo'));
        $book->addLink(new BookLink()->setKind('website')->setUrl('https://example.org'));

        return $book;
    }
}
