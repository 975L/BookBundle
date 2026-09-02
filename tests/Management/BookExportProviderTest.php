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
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Management\BookExportProvider;
use c975L\BookBundle\Management\BookImportProvider;
use c975L\BookBundle\Management\MediaArchiver;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Tests\ArchiveTestTrait;
use c975L\UiBundle\Management\BlockDataExporter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class BookExportProviderTest extends TestCase
{
    use ArchiveTestTrait;

    public function testGetKindMatchesBookImportProvider(): void
    {
        $this->assertSame(BookImportProvider::KIND, $this->createProvider(sys_get_temp_dir())->getKind());
    }

    public function testExportAllSerializesEveryBookFromTheRepository(): void
    {
        $book = new Book()
            ->setSlug('contes-du-soir')
            ->setTitle('Contes du Soir')
            ->setAuthor(self::contributor())
            ->setSummary('Une histoire')
            ->setCreation(new \DateTime('2026-01-02 10:00:00'))
            ->setModification(new \DateTime('2026-01-03 11:00:00'));

        $bookRepository = $this->createMock(BookRepository::class);
        // findBy([]) rather than a published-only query: the trash travels too
        $bookRepository->expects($this->once())->method('findBy')->with([])->willReturn([$book]);

        $data = $this->createProvider(sys_get_temp_dir(), $bookRepository)->exportAll();

        $this->assertCount(1, $data['items']);
        $this->assertSame('contes-du-soir', $data['items'][0]['slug']);
        $this->assertSame('Contes du Soir', $data['items'][0]['title']);
        $this->assertFalse($data['items'][0]['isDeleted']);
        $this->assertSame([], $data['files']);
    }

    // What the index's "Export selection" batch action hands to ContentExporter (see TrashableCrudTrait::exportSelection())
    public function testSerializeIdsExportsOnlyTheCheckedRows(): void
    {
        $bookRepository = $this->createMock(BookRepository::class);
        $bookRepository->expects($this->once())->method('findBy')->with(['id' => [4, 7]])->willReturn([$this->createBook()]);

        $data = $this->createProvider(sys_get_temp_dir(), $bookRepository)->serializeIds([4, 7]);

        $this->assertSame(['tome-1'], array_column($data['items'], 'slug'));
    }

    public function testSerializeNamesTheSerieAndTheTranslatedBookBySlug(): void
    {
        $serie = new Serie()->setSlug('contes-du-soir')->setTitle('Contes du Soir');
        $translated = new Book()->setSlug('evening-tales')->setTitle('Evening Tales')->setAuthor(self::contributor('LM', 'lm'))->setSummary('');
        $book = $this->createBook()->setSerie($serie)->setTranslationBook($translated);

        $item = $this->createProvider(sys_get_temp_dir())->serialize([$book])['items'][0];

        $this->assertSame('contes-du-soir', $item['serie']);
        $this->assertSame('Contes du Soir', $item['serieTitle']);
        $this->assertSame('evening-tales', $item['translationBook']);
    }

    public function testSerializeCarriesTheFormatsAndTheFilesAndPlatformsOfTheBook(): void
    {
        $projectDir = $this->createProjectDir(['medias/book/books/cover-tome-1/c.webp' => 'cover-bytes']);

        $book = $this->createBook();
        $edition = new BookEdition()->setKind('paperback')->setIsbn('9781234567897')->setPages(48)->setFormat('15 x 21 cm')->setPosition(0);
        $book->addEdition($edition);
        $book->addMedia(new BookMedia()->setName('medias/book/books/cover-tome-1/c.webp')->setKind('cover')->setTitle('Couverture')->setPosition(0)->setUpdatedAt(new \DateTimeImmutable('2026-02-01 09:00:00')));
        $book->addLink(new BookLink()->setKind('epub_kobo')->setUrl('https://kobo.example/livre?aff=975l')->setPosition(1));

        $item = $this->createProvider($projectDir)->serialize([$book])['items'][0];

        $this->assertSame([['kind' => 'paperback', 'isbn' => '9781234567897', 'pages' => 48, 'format' => '15 x 21 cm', 'position' => 0]], $item['editions']);
        // The whole address is stored and carried, the affiliate identifier included
        $this->assertSame('https://kobo.example/livre?aff=975l', $item['links'][0]['url']);
        // Neither the file nor the platform names an edition: they belong to the book (see BookEditionType)
        $this->assertArrayNotHasKey('edition', $item['links'][0]);
        $this->assertArrayNotHasKey('edition', $item['medias'][0]);
        $this->assertSame('medias/book/books/cover-tome-1/c.webp', $item['medias'][0]['name']);

        $this->removeDir($projectDir);
    }

    public function testSerializeDropsAMediaWhoseFileHasLeftTheDiskAndKeepsAYoutubeOne(): void
    {
        $projectDir = $this->createProjectDir([]);

        $book = $this->createBook();
        $book->addMedia(new BookMedia()->setName('medias/book/books/cover-tome-1/gone.webp')->setKind('cover')->setUpdatedAt(new \DateTimeImmutable()));
        $book->addVideo(new BookVideo()->setYoutubeUrl('https://youtu.be/abc')->setUpdatedAt(new \DateTimeImmutable()));

        $item = $this->createProvider($projectDir)->serialize([$book])['items'][0];

        $this->assertSame([], $item['medias']);
        $this->assertCount(1, $item['videos']);
        $this->assertSame('https://youtu.be/abc', $item['videos'][0]['youtubeUrl']);
        $this->assertNull($item['videos'][0]['file']);

        $this->removeDir($projectDir);
    }

    private function createBook(): Book
    {
        return new Book()
            ->setSlug('tome-1')
            ->setTitle('Tome 1')
            ->setAuthor(self::contributor())
            ->setSummary('Résumé')
            ->setCreation(new \DateTime('2026-01-02 10:00:00'))
            ->setModification(new \DateTime('2026-01-03 11:00:00'));
    }

    private function createProvider(string $projectDir, ?BookRepository $bookRepository = null): BookExportProvider
    {
        return new BookExportProvider(
            $bookRepository ?? $this->createStub(BookRepository::class),
            new BlockDataExporter($projectDir),
            new MediaArchiver($this->createStub(EntityManagerInterface::class), $projectDir),
        );
    }

    // The person the fixtures credit, a row of their own now that a name is no longer a column (see Entity\Contributor)
    private static function contributor(string $name = 'Laurent Marquet', string $slug = 'laurent-marquet'): Contributor
    {
        return new Contributor()->setName($name)->setSlug($slug);
    }
}
