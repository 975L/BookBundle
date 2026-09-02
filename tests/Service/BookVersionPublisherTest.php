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
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Service\BookVersionPublisher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

// Publishing a new version: the book keeps its identity, the twin takes the version set aside
class BookVersionPublisherTest extends TestCase
{
    // The twin is the same book under another title: same number, same language, same author, and it names the one replacing it
    public function testThePreviousVersionIsTheSameBookUnderAnotherTitle(): void
    {
        $book = new Book()
            ->setTitle('Chat et Chocolat')
            ->setSlug('chat-et-chocolat')
            ->setNumber(12)
            ->setLanguage('fr')
            ->setAuthor(new Contributor()->setName('Contes du Soir')->setSlug('contes-du-soir'))
            ->setData(['idea' => 'Melvin, 5 ans']);

        $previous = $this->publisher()->createPreviousVersion($book, 'Chat et Chocolat — Édition originale');

        $this->assertSame('Chat et Chocolat — Édition originale', $previous->getTitle());
        $this->assertSame('chat-et-chocolat-previous', $previous->getSlug());
        $this->assertSame(12, $previous->getNumber());
        $this->assertSame('fr', $previous->getLanguage());
        $this->assertSame('Contes du Soir', $previous->getAuthor()?->getName());
        $this->assertSame(['idea' => 'Melvin, 5 ans'], $previous->getData());
        $this->assertSame($book, $previous->getNewerVersion());
        // The book itself did not move address: it is the one outside links carry
        $this->assertSame('chat-et-chocolat', $book->getSlug());
    }

    // A third version chains onto the second: the sequence the screen plays - free the previous version, bring the twin to life, hang the old one behind it (see BookCrudController::publishVersion())
    public function testAThirdVersionChainsOnTheSecond(): void
    {
        $book = new Book()->setTitle('Chat et Chocolat')->setSlug('chat-et-chocolat');
        $first = new Book()->setTitle('Chat et Chocolat — Édition originale')->setNewerVersion($book);

        $first->setNewerVersion(null);
        $second = $this->publisher()->createPreviousVersion($book, 'Chat et Chocolat — Édition revue');
        $first->setNewerVersion($second);

        // The book is replaced by the twin, the twin by nothing, and the previous version is reached behind it
        $this->assertSame($book, $second->getNewerVersion());
        $this->assertSame($second, $book->getPreviousVersion());
        $this->assertSame($second, $first->getNewerVersion());
        $this->assertSame($first, $second->getPreviousVersion());
    }

    // A moved edition takes the ISBN and the date the text came out under, and nothing else: the files and the platforms belong to the book (see BookEditionType)
    public function testMovingAFormatTakesItsCivilStatusAlone(): void
    {
        $book = new Book()->setTitle('Chat et Chocolat')->setSlug('chat-et-chocolat');
        $edition = new BookEdition()->setKind('digital')->setIsbn('9791092030143');
        $link = new BookLink()->setKind('epub_kobo');

        $book->addEdition($edition);
        $book->addLink($link);

        $previous = new Book()->setTitle('Chat et Chocolat — Édition originale');
        $this->publisher()->moveEdition($edition, $previous);

        $this->assertSame($previous, $edition->getBook());
        $this->assertSame('9791092030143', $edition->getIsbn());
        // The platform stays with the book keeping the page: it is the one the reader reaches
        $this->assertSame($book, $link->getBook());
    }

    // The version bearing that name, null for a book publishing none under it
    public function testTheEditionIsFoundByTheNameItIsPublishedUnder(): void
    {
        $book = new Book();
        $audio = new BookEdition()->setKind('audio');
        $book->addEdition($audio);

        $this->assertSame($audio, $this->publisher()->editionOfKind($book, 'audio'));
        $this->assertNull($this->publisher()->editionOfKind($book, 'paper'));
    }

    private function publisher(?Book $collides = null): BookVersionPublisher
    {
        $repository = $this->createStub(BookRepository::class);
        $repository->method('findOneBy')->willReturn($collides);

        return new BookVersionPublisher($repository, new AsciiSlugger());
    }

    // The pages of the text as it came out follow the version set aside; what holds for the text itself - the recording, the trailer - stays with the book keeping the page
    public function testThePagesFollowTheRelegatedVersionAndTheRecordingStays(): void
    {
        $book = new Book()->setTitle('Chat et Chocolat');
        $previous = new Book()->setTitle('Chat et Chocolat - Édition originale');

        $extract = new BookMedia()->setKind('extract');
        $trailer = new BookMedia()->setKind('trailer');
        $audio = new BookMedia()->setKind('audio_mp3');
        foreach ([$extract, $trailer, $audio] as $media) {
            $book->addMedia($media);
        }

        $moved = $this->publisher()->moveMedias($book, $previous, ['extract', 'flipbook']);

        $this->assertSame(1, $moved);
        $this->assertSame($previous, $extract->getBook());
        $this->assertSame($book, $trailer->getBook());
        $this->assertSame($book, $audio->getBook());
    }
}
