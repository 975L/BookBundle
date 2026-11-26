<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Command;

use c975L\BookBundle\Command\BookPublishCommand;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookCategory;
use c975L\BookBundle\Entity\BookEdition;
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Enum\BookEditionFileKind;
use c975L\BookBundle\Repository\BookCategoryRepository;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Repository\ContributorRepository;
use c975L\BookBundle\Repository\SerieRepository;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\BookShopPublisher;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

// A book written from a manifest: created or brought up to date by its slug, its medias and editions laid, visible from its day on, written into the shop, refused whole on what it cannot find
class BookPublishCommandTest extends TestCase
{
    private string $directory;

    private Serie $serie;

    private bool $flushed = false;

    private bool $shopped = false;

    /** @var array<string, Book> */
    private array $books = [];

    /** @var array<string, mixed> */
    private array $serieCriteria = [];

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/book-publish-' . uniqid();
        foreach (['cover.jpg', 'page2.jpg', 'book.pdf', 'book.epub', 'book.mp3'] as $file) {
            new Filesystem()->dumpFile($this->directory . '/' . $file, $file);
        }
        $this->serie = new Serie()->setSlug('catalog')->setAuthor(new Contributor()->setSlug('item-3'));
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    // A new book takes the manifest's texts, day, serie, translation, medias and editions, is shown from its day, written into the shop and its address printed last
    public function testANewBookIsCreatedWithItsEditions(): void
    {
        $source = new Book()->setSlug('item-0');
        $this->books['item-0'] = $source;
        $tester = $this->publish([
            'language' => 'en',
            'summary' => '<p>item-1</p>',
            'translates' => 'item-0',
            'categories' => ['catalog', 'item-9'],
            'contributors' => [['contributor' => 'item-2', 'role' => 'narrator']],
            'medias' => ['cover' => 'cover.jpg', 'page' => ['cover.jpg', 'page2.jpg']],
            'editions' => [
                ['kind' => 'digital', 'isbn' => '9780000000001', 'pages' => 16, 'price' => 0, 'channels' => ['shop', 'onix'], 'files' => ['pdf' => 'book.pdf', 'epub' => ['file' => 'book.epub', 'readAloud' => true]]],
                ['kind' => 'audio', 'isbn' => '9780000000002', 'files' => ['audio' => 'book.mp3', 'cover_front' => 'cover.jpg']],
            ],
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertTrue($this->flushed);
        $this->assertTrue($this->shopped);
        $book = $source->getTranslationBook();
        $this->assertInstanceOf(Book::class, $book);
        $this->assertSame([$source, 'item-1', 'en', '2026-12-03', false], [$book->getTranslationBook(), $book->getSlug(), $book->getLanguage(), $book->getPublished()?->format('Y-m-d'), $book->isHidden()]);
        $this->assertSame($this->serie->getAuthor(), $book->getAuthor());
        $this->assertCount(1, $book->getCategories());
        $this->assertCount(1, $book->getContributors());
        $this->assertSame(['cover', 'page', 'page'], array_map(static fn (BookMedia $media): ?string => $media->getKind(), $book->getMedias()->toArray()));
        $digital = $book->getEdition('digital');
        $this->assertInstanceOf(BookEdition::class, $digital);
        $this->assertSame(['9780000000001', 16, ['shop', 'onix']], [$digital->getIsbn(), $digital->getPages(), $digital->getChannels()]);
        $this->assertTrue($digital->getFileOf(BookEditionFileKind::Epub)?->isReadAloud());
        $this->assertSame(0, $digital->getFileOf(BookEditionFileKind::Pdf)?->getPrice());
        $this->assertNull($book->getEdition('audio')?->getFileOf(BookEditionFileKind::CoverFront)?->getPrice());
        $this->assertStringContainsString('item-9', $tester->getDisplay());
        $this->assertStringContainsString('BOOK', $tester->getDisplay());
    }

    // The book of that slug is brought up to date, its medias and editions kept when the manifest names none
    public function testTheSameBookIsUpdated(): void
    {
        $book = new Book()->setSlug('item-1')->setTitle('item-0')->setSummary('summary-0')->setHidden(true);
        $book->addMedia(new BookMedia()->setKind('cover'));
        $this->books['item-1'] = $book;

        $this->assertSame(Command::SUCCESS, $this->publish([])->getStatusCode());
        $this->assertSame(['item-1', 'summary-0', false], [$book->getTitle(), $book->getSummary(), $book->isHidden()]);
        $this->assertCount(1, $book->getMedias());
    }

    // A missing file, an unknown kind, a book to translate that is not there or a malformed day refuse the manifest, nothing written
    public function testWhatCannotBeFoundWritesNothing(): void
    {
        $this->assertSame(Command::FAILURE, $this->publish(['medias' => ['cover' => '../item-9.jpg']])->getStatusCode());
        $this->assertSame(Command::FAILURE, $this->publish(['medias' => ['item-9' => 'cover.jpg']])->getStatusCode());
        $this->assertSame(Command::FAILURE, $this->publish(['editions' => [['kind' => 'digital', 'channels' => ['item-9']]]])->getStatusCode());
        $this->assertSame(Command::FAILURE, $this->publish(['editions' => [['kind' => 'digital', 'files' => ['pdf' => 'item-9.pdf']]]])->getStatusCode());
        $this->assertSame(Command::FAILURE, $this->publish(['translates' => 'item-9'])->getStatusCode());
        $this->assertSame(Command::FAILURE, $this->publish(['published' => '03/12/2026'])->getStatusCode());
        $this->assertFalse($this->flushed);
    }

    // A file in an extension its kind does not take, a file its edition does not hold, a value its column cannot hold, a language the back office does not offer, a slug no address takes or a book translating itself refuse the manifest, nothing written
    public function testWhatTheCatalogCannotHoldWritesNothing(): void
    {
        $this->assertSame(Command::FAILURE, $this->publish(['medias' => ['cover' => 'book.json']])->getStatusCode());
        $this->assertSame(Command::FAILURE, $this->publish(['editions' => [['kind' => 'digital', 'files' => ['epub' => 'book.pdf']]]])->getStatusCode());
        $this->assertSame(Command::FAILURE, $this->publish(['editions' => [['kind' => 'audio', 'files' => ['pdf' => 'book.pdf']]]])->getStatusCode());
        $this->assertSame(Command::FAILURE, $this->publish(['editions' => [['kind' => 'digital', 'isbn' => '978-0-00-000000-1']]])->getStatusCode());
        $this->assertSame(Command::FAILURE, $this->publish(['editions' => [['kind' => 'digital', 'files' => ['pdf' => ['file' => 'book.pdf', 'price' => -1]]]]])->getStatusCode());
        $this->assertSame(Command::FAILURE, $this->publish(['language' => 'xx'])->getStatusCode());
        $this->assertSame(Command::FAILURE, $this->publish(['age' => str_repeat('4', 21)])->getStatusCode());
        $this->assertSame(Command::FAILURE, $this->publish(['slug' => 'Item 1/été'])->getStatusCode());
        $this->assertSame(Command::FAILURE, $this->publish(['translates' => 'item-1'])->getStatusCode());
        $this->assertFalse($this->flushed);
    }

    // A serie is looked for outside the trash
    public function testASerieInTheTrashIsNotFound(): void
    {
        $this->serieCriteria = [];
        $this->publish([]);
        $this->assertSame(['slug' => 'catalog', 'isDeleted' => false], $this->serieCriteria);
    }

    // A book of that slug in the trash refuses the manifest, left as it was
    public function testABookInTheTrashWritesNothing(): void
    {
        $book = new Book()->setSlug('item-1')->setTitle('item-0')->setIsDeleted(true);
        $this->books['item-1'] = $book;

        $this->assertSame(Command::FAILURE, $this->publish([])->getStatusCode());
        $this->assertFalse($this->flushed);
        $this->assertSame('item-0', $book->getTitle());
    }

    // The command run on a manifest of book item-1 of the serie, the given keys laid over its own
    /** @param array<string, mixed> $manifest */
    private function publish(array $manifest): CommandTester
    {
        new Filesystem()->dumpFile($this->directory . '/book.json', (string) json_encode([...['slug' => 'item-1', 'serie' => 'catalog', 'title' => 'item-1', 'published' => '2026-12-03'], ...$manifest]));
        $series = $this->createStub(SerieRepository::class);
        $series->method('findOneBy')->willReturnCallback(function (array $criteria): Serie {
            $this->serieCriteria = $criteria;

            return $this->serie;
        });
        $books = $this->createStub(BookRepository::class);
        $books->method('findOneBy')->willReturnCallback(fn (array $criteria): ?Book => $this->books[$criteria['slug']] ?? null);
        $categories = $this->createStub(BookCategoryRepository::class);
        $categories->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?BookCategory => 'catalog' === $criteria['slug'] ? new BookCategory() : null);
        $contributors = $this->createStub(ContributorRepository::class);
        $contributors->method('findOneBy')->willReturn(new Contributor()->setSlug('item-2'));
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('flush')->willReturnCallback(function (): void {
            $this->flushed = true;
        });
        $urls = $this->createStub(BookPublicUrlResolver::class);
        $urls->method('resolve')->willReturn('https://example.com/item-1');
        $shop = $this->createStub(BookShopPublisher::class);
        $shop->method('isAvailable')->willReturn(true);
        $shop->method('publish')->willReturnCallback(function (): void {
            $this->shopped = true;
        });
        $tester = new CommandTester(new BookPublishCommand($em, $books, $series, $categories, $contributors, $urls, $shop));
        $tester->execute(['manifest' => $this->directory . '/book.json']);

        return $tester;
    }
}
