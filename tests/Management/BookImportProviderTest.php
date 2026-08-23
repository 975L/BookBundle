<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Management;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookEdition;
use c975L\BookBundle\Entity\BookLink;
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Entity\BookVideo;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Management\BookExportProvider;
use c975L\BookBundle\Management\BookImportProvider;
use c975L\BookBundle\Management\MediaArchiver;
use c975L\BookBundle\Management\SerieResolver;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Repository\SerieRepository;
use c975L\BookBundle\Tests\ArchiveTestTrait;
use c975L\UiBundle\Management\BlockDataExporter;
use c975L\UiBundle\Management\BlockDataImporter;
use c975L\UiBundle\Registry\FormBlockDependencyRegistry;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class BookImportProviderTest extends TestCase
{
    use ArchiveTestTrait;

    public function testSupportsOnlyItsOwnKind(): void
    {
        $provider = $this->createProvider(sys_get_temp_dir());

        $this->assertTrue($provider->supportsImport(BookImportProvider::KIND));
        $this->assertFalse($provider->supportsImport('gallery_category'));
    }

    // The whole trip a "export sync all" archive makes: exported on one site, extracted on another, imported into a catalog that holds nothing yet
    public function testRoundTripRebuildsTheBookItsVersionsItsPlatformsAndItsFiles(): void
    {
        $sourceDir = $this->createProjectDir(['medias/book/books/cover-tome-1/c.webp' => 'cover-bytes']);
        $export = $this->createExportProvider($sourceDir)->serialize([$this->createFullBook()]);

        $filesDir = $this->extractArchive($export['files']);
        $targetDir = $this->createProjectDir([]);
        $persisted = [];

        $result = $this->createProvider($targetDir, persisted: $persisted)->import($export['items'], $filesDir);

        $this->assertSame(['created' => 1, 'updated' => 0], $result);

        $book = $this->firstOf($persisted, Book::class);
        $this->assertSame('tome-1', $book->getSlug());
        $this->assertSame('Tome 1', $book->getTitle());
        $this->assertSame(['dedication' => 'Pour Kalaan'], $book->getData());
        $this->assertSame('2026-01-02 10:00:00', $book->getCreation()->format('Y-m-d H:i:s'));

        // The serie this environment doesn't hold yet, created on the fly rather than dropped
        $this->assertSame('la-guilde', $book->getSerie()?->getSlug());
        $this->assertSame('La Guilde des Seigneurs', $book->getSerie()?->getTitle());

        $edition = $book->getEdition('paperback');
        $this->assertSame('9781234567897', $edition?->getIsbn());
        $this->assertSame(48, $edition?->getPages());

        // The platform and the file came back onto the book, which they belong to (see BookEditionType)
        $this->assertSame('https://kobo.example/livre?aff=975l', $book->getLink('epub_kobo')?->getUrl());
        $this->assertCount(1, $book->getMedias());
        $this->assertSame('medias/book/books/cover-tome-1/c.webp', $book->getMedias()->first()->getName());

        // And the file itself, laid back at the very path it was served under so the site answers at the same urls
        $this->assertSame('cover-bytes', file_get_contents($targetDir . '/public/medias/book/books/cover-tome-1/c.webp'));

        // A media standing for a YouTube url carries no file and travels all the same
        $this->assertSame('https://youtu.be/abc', $book->getVideos()->first()->getYoutubeUrl());

        $this->removeDir($sourceDir);
        $this->removeDir($filesDir);
        $this->removeDir($targetDir);
    }

    public function testImportWritesOverTheBookTheSiteAlreadyHoldsAtThatSlug(): void
    {
        $existing = new Book()->setSlug('tome-1')->setTitle('Ancien titre')->setAuthor('LM')->setSummary('')
            ->setCreation(new \DateTime())->setModification(new \DateTime());
        $existing->addEdition(new BookEdition()->setKind('ebook'));
        $existing->addLink(new BookLink()->setKind('epub_fnac')->setUrl('https://fnac.example/ancien'));

        $result = $this->createProvider(sys_get_temp_dir(), $existing)->import([[
            'slug' => 'tome-1',
            'title' => 'Tome 1',
            'editions' => [['kind' => 'paperback']],
            'links' => [['kind' => 'epub_kobo', 'url' => 'https://kobo.example/livre', 'edition' => 'paperback']],
        ]]);

        $this->assertSame(['created' => 0, 'updated' => 1], $result);
        $this->assertSame('Tome 1', $existing->getTitle());
        // The version and the platform the archive no longer holds are detached, orphanRemoval dropping the rows on flush
        $this->assertSame(['paperback'], array_map(static fn (BookEdition $e) => $e->getKind(), array_values($existing->getEditions()->toArray())));
        $this->assertSame(['epub_kobo'], array_map(static fn (BookLink $l) => $l->getKind(), array_values($existing->getLinks()->toArray())));
    }

    // A file already on disk under that name keeps its row rather than being dropped and built anew, which is what spares a re-import the whole catalog
    public function testImportMatchesAnExistingFileByTheNameItIsServedUnder(): void
    {
        $existing = new Book()->setSlug('tome-1')->setTitle('Tome 1')->setAuthor('LM')->setSummary('')
            ->setCreation(new \DateTime())->setModification(new \DateTime());
        $kept = new BookMedia()->setName('medias/book/books/cover-tome-1/c.webp')->setTitle('Ancien')->setUpdatedAt(new \DateTimeImmutable());
        $dropped = new BookMedia()->setName('medias/book/books/cover-tome-1/old.webp')->setUpdatedAt(new \DateTimeImmutable());
        $existing->addMedia($kept);
        $existing->addMedia($dropped);

        $this->createProvider(sys_get_temp_dir(), $existing)->import([[
            'slug' => 'tome-1',
            'title' => 'Tome 1',
            'medias' => [['name' => 'medias/book/books/cover-tome-1/c.webp', 'kind' => 'cover', 'title' => 'Couverture', 'position' => 0]],
        ]]);

        $this->assertSame([$kept], array_values($existing->getMedias()->toArray()));
        $this->assertSame('Couverture', $kept->getTitle());
    }

    // The archive lists the two in whichever order, so the link is made once every book of the run has been read
    public function testImportLinksATranslationToABookOfTheSameArchiveWhicheverComesFirst(): void
    {
        $persisted = [];
        $this->createProvider(sys_get_temp_dir(), persisted: $persisted)->import([
            ['slug' => 'daddy-hug', 'title' => 'Daddy Hug', 'language' => 'en', 'translationBook' => 'papa-calin'],
            ['slug' => 'papa-calin', 'title' => 'Papa Câlin', 'language' => 'fr'],
        ]);

        $books = array_values(array_filter($persisted, static fn (object $e) => $e instanceof Book));
        $this->assertSame('papa-calin', $books[0]->getTranslationBook()?->getSlug());
        $this->assertNull($books[1]->getTranslationBook());
    }

    // Nothing outside this bundle's own media directory is ever written: what comes out of a zip is a path an admin uploaded
    public function testImportRefusesToLayAFileOutsideTheBundleMediaDirectory(): void
    {
        $filesDir = $this->extractArchiveContent(['files/evil' => 'owned']);
        $targetDir = $this->createProjectDir([]);

        $this->createProvider($targetDir)->import([[
            'slug' => 'tome-1',
            'title' => 'Tome 1',
            'medias' => [['name' => '../../etc/book.webp', 'file' => 'files/evil']],
        ]], $filesDir);

        $this->assertFileDoesNotExist($targetDir . '/public/../../etc/book.webp');

        $this->removeDir($filesDir);
        $this->removeDir($targetDir);
    }

    private function createFullBook(): Book
    {
        $book = new Book()
            ->setSlug('tome-1')
            ->setTitle('Tome 1')
            ->setAuthor('Laurent Marquet')
            ->setSummary('Résumé')
            ->setData(['dedication' => 'Pour Kalaan'])
            ->setCreation(new \DateTime('2026-01-02 10:00:00'))
            ->setModification(new \DateTime('2026-01-03 11:00:00'))
            ->setSerie(new Serie()->setSlug('la-guilde')->setTitle('La Guilde des Seigneurs'));

        $edition = new BookEdition()->setKind('paperback')->setIsbn('9781234567897')->setPages(48)->setPosition(0);
        $book->addEdition($edition);
        $book->addMedia(new BookMedia()->setName('medias/book/books/cover-tome-1/c.webp')->setKind('cover')->setTitle('Couverture')->setPosition(0)->setUpdatedAt(new \DateTimeImmutable('2026-02-01 09:00:00')));
        $book->addLink(new BookLink()->setKind('epub_kobo')->setUrl('https://kobo.example/livre?aff=975l')->setPosition(0));
        $book->addVideo(new BookVideo()->setYoutubeUrl('https://youtu.be/abc')->setUpdatedAt(new \DateTimeImmutable()));

        return $book;
    }

    // @param list<object> $persisted filled with everything the import hands to the entity manager, the flush being a stub
    private function createProvider(string $projectDir, ?Book $existingBook = null, array &$persisted = []): BookImportProvider
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $bookRepository = $this->createStub(BookRepository::class);
        $bookRepository->method('findOneBy')->willReturn($existingBook);

        return new BookImportProvider(
            $em,
            $bookRepository,
            new BlockDataImporter($em, $this->createStub(FormBlockDependencyRegistry::class)),
            new MediaArchiver($em, $projectDir),
            new SerieResolver($em, $this->createStub(SerieRepository::class)),
        );
    }

    private function createExportProvider(string $projectDir): BookExportProvider
    {
        return new BookExportProvider(
            $this->createStub(BookRepository::class),
            new BlockDataExporter($projectDir),
            new MediaArchiver($this->createStub(EntityManagerInterface::class), $projectDir),
        );
    }

    // @param array<string, string> $entries archive-relative path => bytes
    private function extractArchiveContent(array $entries): string
    {
        $filesDir = sys_get_temp_dir() . '/book_archive_test_' . bin2hex(random_bytes(4));
        foreach ($entries as $path => $content) {
            $this->write($filesDir . '/' . $path, $content);
        }

        return $filesDir;
    }

    /**
     * @template T of object
     *
     * @param list<object>    $persisted
     * @param class-string<T> $class
     *
     * @return T
     */
    private function firstOf(array $persisted, string $class): object
    {
        foreach ($persisted as $entity) {
            if ($entity instanceof $class) {
                return $entity;
            }
        }

        $this->fail(sprintf('No %s was persisted.', $class));
    }
}
